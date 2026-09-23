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
use App\Modules\Checkout\Application\PlaceOrderUseCase;
use App\Modules\Checkout\Domain\Sale;
use App\Modules\Checkout\Infrastructure\Conekta\ConektaGateway;

/**
 * Contrato de fallo de /pago/{sale_code}/confirm para Conekta (nivel use case):
 * el confirm SIEMPRE resuelve a paid / pending / failed.
 *
 * Los casos NO confirmables lanzan DomainException con el código HTTP que el
 * controlador traduce a status="failed":
 *   404 venta inexistente
 *   400 monto/moneda que no corresponden a la venta
 *   409 venta cancelada, venta vencida, o estado terminal Conekta
 *       (expired / failed)
 * Los casos confirmables devuelven el resumen con status "paid" o "pending" y
 * la venta/transacción se promueve SOLO cuando la pasarela confirmó.
 */

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$_ENV['PAYPAL_CURRENCY'] = 'MXN';
$_ENV['CONEKTA_CURRENCY'] = 'MXN';

// ---------------------------------------------------------------------------
// Gateway Conekta falso: sin red. getOrder devuelve el estado configurado por
// orderId (Conekta entrega payment_status en minúsculas).
// ---------------------------------------------------------------------------
final class FakeConektaStatusGateway implements PaymentGatewayInterface
{
    /** @var array<string, array<string, mixed>> orderId -> estado de la orden */
    private array $orders = [];

    public function setOrder(string $orderId, string $status, float $amount, string $currency = 'MXN', ?string $reference = null, ?string $paymentMethod = 'card'): void
    {
        $this->orders[$orderId] = [
            'status' => $status,
            'amount' => $amount,
            'currency' => $currency,
            'captureId' => 'cap_' . $orderId,
            'reference' => $reference,
            'paymentMethod' => $paymentMethod,
        ];
    }

    public function createOrder(PaymentOrder $order): string
    {
        return 'ck_' . $order->getReference();
    }

    public function createRedirectCheckout(PaymentOrder $order): PaymentCheckout
    {
        return new PaymentCheckout(orderId: 'ck_' . $order->getReference(), checkoutRequestId: '');
    }

    public function captureOrder(string $orderId): PaymentCapture
    {
        throw new \RuntimeException('Conekta no utiliza captura server-to-server.');
    }

    public function getOrder(string $orderId): PaymentCapture
    {
        $o = $this->orders[$orderId] ?? [
            'status' => 'pending',
            'amount' => 0.0,
            'currency' => 'MXN',
            'captureId' => null,
            'reference' => null,
            'paymentMethod' => null,
        ];

        return new PaymentCapture(
            orderId: $orderId,
            status: (string) $o['status'],
            amount: (float) $o['amount'],
            currency: (string) $o['currency'],
            captureId: $o['captureId'] !== null ? (string) $o['captureId'] : null,
            reference: $o['reference'] !== null ? (string) $o['reference'] : null,
            paymentMethod: $o['paymentMethod'] !== null ? (string) $o['paymentMethod'] : null,
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
$container->singleton(ConektaGateway::class, FakeConektaStatusGateway::class);

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

function newProduct(string $code, float $price, int $stock): int
{
    return (int) $GLOBALS['db']->insert('products', [
        'subcategory_id' => $GLOBALS['subcategoryId'],
        'product_code' => $code,
        'name' => 'Producto Test Conekta ' . $code,
        'stock' => $stock,
        'price' => $price,
        'discount' => 0.00,
        'status' => 'active',
    ]);
}

function newCustomer(string $code): int
{
    return (int) $GLOBALS['db']->insert('customers', [
        'first_name' => 'Cliente',
        'last_name_paternal' => 'Conekta',
        'email' => 'conekta_' . $code . '_' . $GLOBALS['suffix'] . '@example.com',
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

    $products = [];
    foreach (['CS1_' . $suffix] as $code) {
        $prod = $db->selectOne('SELECT product_id AS id FROM products WHERE product_code = :c', ['c' => $code]);
        if ($prod !== null) {
            $products[] = (int) $prod['id'];
        }
    }

    $cust = $db->selectOne('SELECT customer_id AS id FROM customers WHERE email = :e', ['e' => 'conekta_' . $tag . '_' . $suffix . '@example.com']);
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

    foreach ($products as $pid) {
        $db->query('DELETE FROM inventory_movements WHERE product_id = :id', ['id' => $pid]);
        $db->query('DELETE FROM sale_details WHERE product_id = :id', ['id' => $pid]);
        $db->query('DELETE FROM products WHERE product_id = :id', ['id' => $pid]);
    }
}

$saleRepository = $container->make(SaleRepositoryInterface::class);
$placeOrder = $container->make(App\Modules\Checkout\Application\PlaceOrderUseCase::class);
$confirm = $container->make(ConfirmPendingSalePaymentUseCase::class);
$gateway = $container->make(ConektaGateway::class);
$GLOBALS['failed'] = 0;

function expect(string $label, bool $ok): void
{
    echo ($ok ? 'PASS' : 'FAIL') . ": {$label}\n";
    if (!$ok) {
        $GLOBALS['failed']++;
    }
}

function paymentStatusOf(int $saleId): string
{
    return (string) $GLOBALS['db']->selectOne('SELECT payment_status FROM sales WHERE sale_id = :id', ['id' => $saleId])['payment_status'];
}

function txStatusFor(string $orderId): ?string
{
    $row = $GLOBALS['db']->selectOne('SELECT status FROM payment_transactions WHERE provider_order_id = :o', ['o' => $orderId]);
    return $row === null ? null : (string) $row['status'];
}

function txCountFor(string $orderId): int
{
    return (int) $GLOBALS['db']->selectOne('SELECT COUNT(*) AS total FROM payment_transactions WHERE provider_order_id = :o', ['o' => $orderId])['total'];
}

/** Devuelve la DomainException lanzada (o null si el confirm devolvió algo). */
function attemptConfirm(ConfirmPendingSalePaymentUseCase $confirm, int $customerId, int $saleId, string $orderId): ?\DomainException
{
    try {
        $confirm->execute(
            customerId: $customerId,
            saleId: $saleId,
            orderId: $orderId,
            providerId: 'conekta',
        );
        return null;
    } catch (\DomainException $exception) {
        return $exception;
    }
}

try {
    $p1 = newProduct('CS1_' . $suffix, 100.00, 20);
    $customerId = newCustomer('cs_' . $suffix);
    $addressId = newAddress($customerId);

    function newConektaSale(PlaceOrderUseCase $placeOrder, int $customerId, int $addressId, int $productId): Sale
    {
        return $placeOrder->execute(
            customerId: $customerId,
            addressId: $addressId,
            paymentMethod: 'conekta',
            paymentStatus: 'pending',
            cartItems: [['product_id' => $productId, 'quantity' => 1]],
        );
    }

    $saleA = newConektaSale($placeOrder, $customerId, $addressId, $p1);   // tarjeta pagada
    $saleB = newConektaSale($placeOrder, $customerId, $addressId, $p1);   // OXXO pendiente
    $saleC = newConektaSale($placeOrder, $customerId, $addressId, $p1);   // OXXO expirado
    $saleD = newConektaSale($placeOrder, $customerId, $addressId, $p1);   // tarjeta fallida
    $saleE = newConektaSale($placeOrder, $customerId, $addressId, $p1);   // monto distinto
    $saleF = newConektaSale($placeOrder, $customerId, $addressId, $p1);   // moneda distinta
    $saleG = newConektaSale($placeOrder, $customerId, $addressId, $p1);   // venta cancelada
    $saleH = newConektaSale($placeOrder, $customerId, $addressId, $p1);   // venta vencida

    $total = $saleA->getTotal();

    // Escenario A: tarjeta pagada -> paid.
    $orderA = 'ck_A_' . $suffix;
    $gateway->setOrder($orderA, 'paid', $total, 'MXN', null, 'card');

    $resultA = $confirm->execute(customerId: $customerId, saleId: (int) $saleA->getSaleId(), orderId: $orderA, providerId: 'conekta');
    expect('A. tarjeta pagada devuelve status paid', $resultA['status'] === 'paid');
    expect('A. venta promovida a payment_status=paid', paymentStatusOf((int) $saleA->getSaleId()) === 'paid');
    expect('A. transacción completada', txStatusFor($orderA) === 'completed');

    // Escenario B: OXXO pendiente -> pending con referencia.
    $orderB = 'ck_B_' . $suffix;
    $gateway->setOrder($orderB, 'pending', $total, 'MXN', 'OXXO-REF-' . $suffix, 'cash');

    $resultB = $confirm->execute(customerId: $customerId, saleId: (int) $saleB->getSaleId(), orderId: $orderB, providerId: 'conekta');
    expect('B. OXXO pendiente devuelve status pending', $resultB['status'] === 'pending');
    expect('B. referencia OXXO visible', $resultB['reference'] === 'OXXO-REF-' . $suffix);
    expect('B. payment_method granular = cash', $resultB['payment_method'] === 'cash');
    expect('B. venta sigue pending', paymentStatusOf((int) $saleB->getSaleId()) === 'pending');
    expect('B. transacción creada pendiente', txStatusFor($orderB) === 'pending');

    // Escenario C: OXXO expirado en pasarela -> failed 409.
    $orderC = 'ck_C_' . $suffix;
    $gateway->setOrder($orderC, 'expired', $total);
    $excC = attemptConfirm($confirm, $customerId, (int) $saleC->getSaleId(), $orderC);
    expect('C. conekta expirado lanza DomainException 409', $excC !== null && $excC->getCode() === 409);
    expect('C. venta sigue pending', paymentStatusOf((int) $saleC->getSaleId()) === 'pending');
    expect('C. no hay transacción', txCountFor($orderC) === 0);

    // Escenario D: tarjeta fallida en pasarela -> failed 409.
    $orderD = 'ck_D_' . $suffix;
    $gateway->setOrder($orderD, 'failed', $total);
    $excD = attemptConfirm($confirm, $customerId, (int) $saleD->getSaleId(), $orderD);
    expect('D. conekta fallido lanza DomainException 409', $excD !== null && $excD->getCode() === 409);
    expect('D. venta sigue pending', paymentStatusOf((int) $saleD->getSaleId()) === 'pending');

    // Escenario E: monto que no corresponde -> failed 400 (posible fraude).
    $orderE = 'ck_E_' . $suffix;
    $gateway->setOrder($orderE, 'paid', $total + 10.0);
    $excE = attemptConfirm($confirm, $customerId, (int) $saleE->getSaleId(), $orderE);
    expect('E. monto distinto lanza DomainException 400', $excE !== null && $excE->getCode() === 400);
    expect('E. venta sigue pending', paymentStatusOf((int) $saleE->getSaleId()) === 'pending');
    expect('E. no hay transacción', txCountFor($orderE) === 0);

    // Escenario F: moneda distinta -> failed 400.
    $orderF = 'ck_F_' . $suffix;
    $gateway->setOrder($orderF, 'paid', $total, 'USD', null, 'card');
    $excF = attemptConfirm($confirm, $customerId, (int) $saleF->getSaleId(), $orderF);
    expect('F. moneda distinta lanza DomainException 400', $excF !== null && $excF->getCode() === 400);
    expect('F. venta sigue pending', paymentStatusOf((int) $saleF->getSaleId()) === 'pending');

    // Escenario G: venta cancelada -> failed 409 (guard antes de la pasarela).
    $orderG = 'ck_G_' . $suffix;
    $gateway->setOrder($orderG, 'pending', $total);
    $db->query('UPDATE sales SET status = :s WHERE sale_id = :id', ['s' => 'cancelled', 'id' => (int) $saleG->getSaleId()]);
    $excG = attemptConfirm($confirm, $customerId, (int) $saleG->getSaleId(), $orderG);
    expect('G. venta cancelada lanza DomainException 409', $excG !== null && $excG->getCode() === 409);

    // Escenario H: venta vencida (fuera de la ventana de pago) -> failed 409.
    $orderH = 'ck_H_' . $suffix;
    $gateway->setOrder($orderH, 'pending', $total);
    $db->query(
        'UPDATE sales SET sale_date = DATE_SUB(NOW(), INTERVAL 49 HOUR) WHERE sale_id = :id',
        ['id' => (int) $saleH->getSaleId()]
    );
    $excH = attemptConfirm($confirm, $customerId, (int) $saleH->getSaleId(), $orderH);
    expect('H. venta vencida lanza DomainException 409', $excH !== null && $excH->getCode() === 409);

    // Escenario I: venta inexistente -> failed 404.
    $excI = attemptConfirm($confirm, $customerId, 99999999, 'ck_I_' . $suffix);
    expect('I. venta inexistente lanza DomainException 404', $excI !== null && $excI->getCode() === 404);

    // Idempotencia: un segundo confirm de la venta pagada no duplica transacción.
    $retryA = $confirm->execute(customerId: $customerId, saleId: (int) $saleA->getSaleId(), orderId: $orderA, providerId: 'conekta');
    expect('J. reintento sigue siendo paid', $retryA['status'] === 'paid');
    expect('J. reintento NO duplica transacción (sigue 1)', txCountFor($orderA) === 1);

    cleanup('cs_' . $suffix);
} finally {
    cleanup('cs_' . $suffix);
    unset($_ENV['PAYPAL_CURRENCY']);
    unset($_ENV['CONEKTA_CURRENCY']);
}

if ($GLOBALS['failed'] > 0) {
    echo "\n=== FALLARON {$GLOBALS['failed']} verificaciones ===\n";
    exit(1);
}

echo "\n=== CONEKTA CONFIRM paid/pending/failed (contrato /pago/confirm) OK ===\n";