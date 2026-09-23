<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Container;
use App\Core\Database\Database;
use App\Modules\Checkout\Domain\PaymentGatewayInterface;
use App\Modules\Checkout\Domain\PaymentCapture;
use App\Modules\Checkout\Domain\PaymentCheckout;
use App\Modules\Checkout\Domain\PaymentOrder;
use App\Modules\Checkout\Domain\SaleRepositoryInterface;
use App\Modules\Checkout\Persistence\SaleRepository;
use App\Modules\Checkout\Domain\PaymentTransactionRepositoryInterface;
use App\Modules\Checkout\Persistence\PaymentTransactionRepository;
use App\Modules\Checkout\Domain\AddressRepositoryInterface;
use App\Modules\Checkout\Persistence\AddressRepository;
use App\Modules\Products\Domain\ProductRepositoryInterface;
use App\Modules\Products\Persistence\ProductRepository;
use App\Modules\Inventory\Domain\InventoryRepositoryInterface;
use App\Modules\Inventory\Persistence\InventoryRepository;
use App\Modules\Checkout\Application\UseCases\ConfirmPendingSalePaymentUseCase;
use App\Modules\Checkout\Application\UseCases\CreateConektaOrderUseCase;
use App\Modules\Checkout\Infrastructure\Paypal\PaypalGateway;

/**
 * Test E2E del flujo PayPal migrado a /pago (P6): sale pending en el checkout,
 * create-order reconstruido desde la venta persistida, confirm -> paid con
 * descuento de stock una sola vez e idempotente frente a reintentos.
 *
 * El gateway PayPal es un doble registrado en el contenedor bajo el nombre de
 * la clase real (PaypalGateway), así el PaymentGatewayResolver real lo
 * resuelve sin red. Se inyecta PAYPAL_CURRENCY solo en ESTE proceso (no se
 * toca el .env) y se restaura al salir.
 */

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$_ENV['PAYPAL_CURRENCY'] = 'MXN';
$_ENV['CONEKTA_CURRENCY'] = 'MXN';

// ---------------------------------------------------------------------------
// Gateway PayPal falso: sin red. El estado de la orden es mutable para
// simular el resultado tras la aprobación/captura en PayPal.
// ---------------------------------------------------------------------------
final class FakePaypalGateway implements PaymentGatewayInterface
{
    /** @var array<string, string> orderId -> status de la orden */
    private array $statuses = [];

    /** número de veces que se invocó captureOrder (regresión del fix) */
    private int $captureCalls = 0;

    /** última PaymentOrder recibida por createRedirectCheckout (para verificar total) */
    private ?PaymentOrder $lastOrder = null;

    private ?string $lastOrderId = null;

    public function setStatus(string $orderId, string $status): void
    {
        $this->statuses[$orderId] = $status;
    }

    public function captureCount(): int
    {
        return $this->captureCalls;
    }

    public function lastOrder(): ?PaymentOrder
    {
        return $this->lastOrder;
    }

    public function lastOrderId(): ?string
    {
        return $this->lastOrderId;
    }

    public function createOrder(PaymentOrder $order): string
    {
        return 'pp_' . $order->getReference();
    }

    public function createRedirectCheckout(PaymentOrder $order): PaymentCheckout
    {
        $this->lastOrder = $order;
        $this->lastOrderId = 'pporder_' . bin2hex(random_bytes(4));
        return new PaymentCheckout(
            orderId: $this->lastOrderId,
            checkoutRequestId: '',
        );
    }

    public function captureOrder(string $orderId): PaymentCapture
    {
        $this->captureCalls++;
        return $this->getOrder($orderId);
    }

    public function getOrder(string $orderId): PaymentCapture
    {
        $status = $this->statuses[$orderId] ?? 'CREATED';

        return new PaymentCapture(
            orderId: $orderId,
            status: $status,
            amount: $this->lastOrder?->getAmount() ?? 0.0,
            currency: 'MXN',
            captureId: 'cap_' . $orderId,
        );
    }

    public function classifyEvent(string $eventType): ?string
    {
        return null;
    }
}

// ---------------------------------------------------------------------------
// Contenedor y repositorios reales (mismos bindings que en index.php).
// ---------------------------------------------------------------------------
$container = new Container();
$container->instance(Container::class, $container);
$container->singleton(Database::class);
$container->bind(SaleRepositoryInterface::class, SaleRepository::class);
$container->bind(AddressRepositoryInterface::class, AddressRepository::class);
$container->bind(PaymentTransactionRepositoryInterface::class, PaymentTransactionRepository::class);
$container->bind(ProductRepositoryInterface::class, ProductRepository::class);
$container->bind(InventoryRepositoryInterface::class, InventoryRepository::class);
$container->singleton(App\Config\PaypalConfig::class);
$container->singleton(App\Config\ConektaConfig::class);
$container->singleton(PaypalGateway::class, FakePaypalGateway::class);

$db = $container->make(Database::class);
$GLOBALS['db'] = $db;

$suffix = bin2hex(random_bytes(4));
$GLOBALS['suffix'] = $suffix;
$subcategory = $db->selectOne("SELECT subcategory_id FROM subcategories LIMIT 1");
if (!$subcategory) {
    echo "FAIL: no hay subcategorias disponibles\n";
    exit(1);
}
$GLOBALS['subcategoryId'] = (int) $subcategory['subcategory_id'];

function newProduct(string $code, int $stock): int
{
    return (int) $GLOBALS['db']->insert('products', [
        'subcategory_id' => $GLOBALS['subcategoryId'],
        'product_code' => $code,
        'name' => 'Producto Test PayPal ' . $code,
        'stock' => $stock,
        'price' => 100.00,
        'discount' => 0.00,
        'status' => 'active',
    ]);
}

function newCustomer(string $code): int
{
    return (int) $GLOBALS['db']->insert('customers', [
        'first_name' => 'Cliente',
        'last_name_paternal' => 'Paypal',
        'email' => 'paypal_' . $code . '@example.com',
        'password' => password_hash('test', PASSWORD_BCRYPT),
        'active' => 1,
    ]);
}

function newAddress(int $customerId): int
{
    return (int) $GLOBALS['db']->insert('addresses', [
        'customer_id' => $customerId,
        'street' => 'Av. Prueba',
        'number' => '123',
        'neighborhood' => 'Centro',
        'municipality' => 'Test',
        'state' => 'Test',
        'zip_code' => '00000',
        'is_default' => 1,
    ]);
}

function cleanup(string $tag): void
{
    $db = $GLOBALS['db'];
    $suffix = $GLOBALS['suffix'];
    $email = 'paypal_' . $tag . '_' . $suffix . '@example.com';

    $cust = $db->selectOne('SELECT customer_id AS id FROM customers WHERE email = :e', ['e' => $email]);
    if ($cust !== null) {
        $cid = (int) $cust['id'];

        $saleIds = [];
        foreach ($db->query('SELECT sale_id AS sid FROM sales WHERE customer_id = :c', ['c' => $cid]) as $row) {
            $saleIds[] = (int) $row['sid'];
        }

        foreach ($saleIds as $sid) {
            $db->query('DELETE FROM deliveries WHERE sale_id = :id', ['id' => $sid]);
            $db->query('DELETE FROM payment_transactions WHERE sale_id = :id', ['id' => $sid]);
            $db->query('DELETE FROM sale_details WHERE sale_id = :id', ['id' => $sid]);
            $db->query("DELETE FROM inventory_movements WHERE origin_type = 'sale' AND origin_id = :id", ['id' => $sid]);
        }

        if (count($saleIds) > 0) {
            $place = implode(',', array_fill(0, count($saleIds), '?'));
            $db->query("DELETE FROM sales WHERE sale_id IN ($place)", $saleIds);
        }

        $db->query('DELETE FROM addresses WHERE customer_id = :id', ['id' => $cid]);
        $db->query('DELETE FROM customers WHERE customer_id = :id', ['id' => $cid]);
    }
}

$saleRepository = $container->make(SaleRepositoryInterface::class);
$placeOrder = $container->make(App\Modules\Checkout\Application\PlaceOrderUseCase::class);
$createOrder = $container->make(CreateConektaOrderUseCase::class);
$confirm = $container->make(ConfirmPendingSalePaymentUseCase::class);
$gateway = $container->make(PaypalGateway::class);
$GLOBALS['failed'] = 0;

function expect(string $label, bool $ok): void
{
    echo ($ok ? 'PASS' : 'FAIL') . ": {$label}\n";
    if (!$ok) {
        $GLOBALS['failed']++;
    }
}

function stockOf(int $productId): int
{
    return (int) $GLOBALS['db']->selectOne('SELECT stock FROM products WHERE product_id = :id', ['id' => $productId])['stock'];
}

function movementCount(int $saleId): int
{
    return (int) $GLOBALS['db']->selectOne(
        "SELECT COUNT(*) AS total FROM inventory_movements WHERE origin_type = 'sale' AND origin_id = :sid",
        ['sid' => $saleId]
    )['total'];
}

try {
    $p1 = newProduct('PP1_' . $suffix, 10);
    $c1 = newCustomer('pp1_' . $suffix);
    $a1 = newAddress($c1);

    // 1. placePaypalOrder (checkout): venta pending SIN descontar stock.
    $sale = $placeOrder->execute(
        customerId: $c1,
        addressId: $a1,
        paymentMethod: 'paypal',
        paymentStatus: 'pending',
        cartItems: [['product_id' => $p1, 'quantity' => 2]],
    );

    $saleId = (int) $sale->getSaleId();
    $saleCode = $sale->getSaleCode();

    expect('1. venta creada como paypal/pending', $sale->getPaymentMethod() === 'paypal'
        && $saleRepository->findBySaleCode($saleCode)?->getPaymentStatus() === 'pending');
    expect('1. pending NO descuenta stock (sigue 10)', stockOf($p1) === 10);
    expect('1. sin movimientos de sale en pending', movementCount($saleId) === 0);

    // 2. create-order desde /pago: reconstruye la orden desde la venta persistida.
    $details = $saleRepository->findDetailsBySaleId($saleId);
    $cartItems = [];
    foreach ($details as $d) {
        $cartItems[] = ['product_id' => (int) $d['product_id'], 'quantity' => (int) $d['quantity']];
    }

    $checkout = $createOrder->execute(
        customerId: $c1,
        addressId: $a1,
        buyer: null,
        cartItems: $cartItems,
        providerId: 'paypal',
    );

    $orderId = $checkout->orderId();
    $lastOrder = $gateway->lastOrder();

    expect('2. create-order basado en la venta (order_id devuelto)', $orderId !== '' && $lastOrder !== null);
    expect('2. monto de la orden == total de la venta (' . $sale->getTotal() . ')',
        $lastOrder !== null && abs($lastOrder->getAmount() - $sale->getTotal()) < 0.001);
    expect('2. create-order NO descuenta stock (sigue 10)', stockOf($p1) === 10);

    // 3. confirm -> paid con descuento de stock (captura síncrona remota y ya aprobada).
    $gateway->setStatus($orderId, 'COMPLETED');

    $result = $confirm->execute(
        customerId: $c1,
        saleId: $saleId,
        orderId: $orderId,
        providerId: 'paypal',
        deductStock: true,
    );

    expect('3. confirm devuelve status paid', $result['status'] === 'paid');
    expect('3. el confirm invocó captureOrder para PayPal (regresión del fix)', $gateway->captureCount() === 1);
    expect('3. venta promovida a paid',
        $saleRepository->findSaleById($saleId)?->getPaymentStatus() === 'paid');
    expect('3. stock descontado una sola vez (10 -> 8)', stockOf($p1) === 8);
    expect('3. exactamente un movimiento de sale', movementCount($saleId) === 1);

    // 4. Reintento del mismo confirm: idempotente, no vuelve a descontar.
    $retry = $confirm->execute(
        customerId: $c1,
        saleId: $saleId,
        orderId: $orderId,
        providerId: 'paypal',
        deductStock: true,
    );
    expect('4. reintento sigue pagado', $retry['status'] === 'paid');
    expect('4. el reintento también invoca captureOrder (captureCount=2)', $gateway->captureCount() === 2);
    expect('4. reintento no duplica descuento (sigue 8)', stockOf($p1) === 8);
    expect('4. reintento no agrega movimientos', movementCount($saleId) === 1);

    // 5. Captura rechazada por la pasarela (DECLINED): la confirmación FALLA
    // sin promover la venta ni descontar stock (contrato failed -> 400).
    $p2 = newProduct('PP2_' . $suffix, 10);
    $c2 = newCustomer('pp2_' . $suffix);
    $a2 = newAddress($c2);

    $sale2 = $placeOrder->execute(
        customerId: $c2,
        addressId: $a2,
        paymentMethod: 'paypal',
        paymentStatus: 'pending',
        cartItems: [['product_id' => $p2, 'quantity' => 1]],
    );
    $sale2Id = (int) $sale2->getSaleId();

    $details2 = $saleRepository->findDetailsBySaleId($sale2Id);
    $cart2 = [];
    foreach ($details2 as $d) {
        $cart2[] = ['product_id' => (int) $d['product_id'], 'quantity' => (int) $d['quantity']];
    }

    $checkout2 = $createOrder->execute(
        customerId: $c2,
        addressId: $a2,
        buyer: null,
        cartItems: $cart2,
        providerId: 'paypal',
    );
    $order2Id = $checkout2->orderId();

    // PayPal reporta el rechazo en MAYÚSCULAS (DECLINED) en la captura.
    $gateway->setStatus($order2Id, 'DECLINED');

    $rejected = null;
    try {
        $confirm->execute(
            customerId: $c2,
            saleId: $sale2Id,
            orderId: $order2Id,
            providerId: 'paypal',
            deductStock: true,
        );
    } catch (\DomainException $exception) {
        $rejected = $exception;
    }

    expect('5. captura DECLINED lanza DomainException 400 (failed)',
        $rejected !== null && $rejected->getCode() === 400);
    expect('5. el estado negativo produjo una captura (captureCount=3)', $gateway->captureCount() === 3);
    expect('5. la venta rechazada sigue pending',
        $saleRepository->findSaleById($sale2Id)?->getPaymentStatus() === 'pending');
    expect('5. sin descuento de stock en rechazo (sigue 10)', stockOf($p2) === 10);
    expect('5. sin movimientos de sale en rechazo', movementCount($sale2Id) === 0);

    // 6. Recuperación: una vez COMPLETED, la misma venta SÍ se confirma.
    $gateway->setStatus($order2Id, 'COMPLETED');
    $recovered = $confirm->execute(
        customerId: $c2,
        saleId: $sale2Id,
        orderId: $order2Id,
        providerId: 'paypal',
        deductStock: true,
    );
    expect('6. la venta se recupera a paid tras completar la captura', $recovered['status'] === 'paid');
    expect('6. stock descontado una sola vez (10 -> 9)', stockOf($p2) === 9);

    cleanup('pp1');
    cleanup('pp2');
} finally {
    cleanup('pp1');
    cleanup('pp2');
    unset($_ENV['PAYPAL_CURRENCY']);
    unset($_ENV['CONEKTA_CURRENCY']);
}

if ($GLOBALS['failed'] > 0) {
    echo "\n=== FALLARON {$GLOBALS['failed']} verificaciones ===\n";
    exit(1);
}

echo "\n=== FLUJO PAYPAL -> /pago (P6) OK ===\n";