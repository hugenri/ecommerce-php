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
use App\Modules\Checkout\Infrastructure\Paypal\PaypalGateway;

/**
 * Test de seguridad: confirmar una venta con el order_id de otra venta cuyo
 * monto no coincide (hallazgo ALTO de la auditoría) debe fallar sin marcar la
 * venta como pagada ni descontar stock.
 *
 * Cubre:
 *   1. Confirmar una venta cara con el order_id legítimo de una venta barata
 *      (mismo cliente, misma pasarela, realmente pagada en la pasarela):
 *      lanza DomainException, la venta queda pending y NO descuenta stock.
 *   2. Confirmar la misma venta con su order_id correcto: funciona igual que
 *      antes (paid + descuento de stock una sola vez).
 *   3. Monto correcto pero moneda distinta: también falla.
 */

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$_ENV['PAYPAL_CURRENCY'] = 'MXN';
$_ENV['CONEKTA_CURRENCY'] = 'MXN';

// ---------------------------------------------------------------------------
// Gateway PayPal falso: sin red. El monto, la moneda y el estado de cada orden
// se configuran manualmente para simular que una orden está realmente pagada
// en la pasarela pero corresponde a otra venta.
// ---------------------------------------------------------------------------
final class FakeAmountGateway implements PaymentGatewayInterface
{
    /** @var array<string, float> orderId -> monto capturado */
    private array $amounts = [];

    /** @var array<string, string> orderId -> moneda capturada */
    private array $currencies = [];

    /** @var array<string, string> orderId -> estado de la orden */
    private array $statuses = [];

    public function setCapture(string $orderId, float $amount, string $currency = 'MXN', string $status = 'COMPLETED'): void
    {
        $this->amounts[$orderId] = $amount;
        $this->currencies[$orderId] = $currency;
        $this->statuses[$orderId] = $status;
    }

    public function createOrder(PaymentOrder $order): string
    {
        return 'pp_' . $order->getReference();
    }

    public function createRedirectCheckout(PaymentOrder $order): PaymentCheckout
    {
        throw new \RuntimeException('No usado en el test.');
    }

    public function captureOrder(string $orderId): PaymentCapture
    {
        return $this->getOrder($orderId);
    }

    public function getOrder(string $orderId): PaymentCapture
    {
        return new PaymentCapture(
            orderId: $orderId,
            status: $this->statuses[$orderId] ?? 'COMPLETED',
            amount: $this->amounts[$orderId] ?? 0.0,
            currency: $this->currencies[$orderId] ?? 'MXN',
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
$container->singleton(PaypalGateway::class, FakeAmountGateway::class);

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
        'name' => 'Producto Test Confirm ' . $code,
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
        'last_name_paternal' => 'Confirm',
        'email' => 'confirm_' . $code . '_' . $GLOBALS['suffix'] . '@example.com',
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
    foreach (['AM1_' . $suffix, 'AM2_' . $suffix] as $code) {
        $prod = $db->selectOne('SELECT product_id AS id FROM products WHERE product_code = :c', ['c' => $code]);
        if ($prod !== null) {
            $products[] = (int) $prod['id'];
        }
    }

    $cust = $db->selectOne('SELECT customer_id AS id FROM customers WHERE email = :e', ['e' => 'confirm_' . $tag . '_' . $suffix . '@example.com']);
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

function expectsMismatch(ConfirmPendingSalePaymentUseCase $confirm, int $customerId, int $saleId, string $orderId): bool
{
    try {
        $confirm->execute(
            customerId: $customerId,
            saleId: $saleId,
            orderId: $orderId,
            providerId: 'paypal',
            deductStock: true,
        );
        return false;
    } catch (\DomainException $exception) {
        return true;
    }
}

try {
    $pCheap = newProduct('AM1_' . $suffix, 100.00, 5);
    $pExpensive = newProduct('AM2_' . $suffix, 2000.00, 10);
    $customerId = newCustomer('am_' . $suffix);
    $addressId = newAddress($customerId);

    // Venta barata X y venta cara Y del mismo cliente, ambas pending.
    $cheapSale = $placeOrder->execute(
        customerId: $customerId,
        addressId: $addressId,
        paymentMethod: 'paypal',
        paymentStatus: 'pending',
        cartItems: [['product_id' => $pCheap, 'quantity' => 1]],
    );
    $expensiveSale = $placeOrder->execute(
        customerId: $customerId,
        addressId: $addressId,
        paymentMethod: 'paypal',
        paymentStatus: 'pending',
        cartItems: [['product_id' => $pExpensive, 'quantity' => 2]],
    );

    expect('setup. caro > barato', $expensiveSale->getTotal() > $cheapSale->getTotal());

    // ----------------------------------------------------------------------
    // 1. Ataque: order_id de la venta barata (realmente pagado en la pasarela
    //    por el monto de la venta barata) usado para confirmar la venta cara.
    // ----------------------------------------------------------------------
    $cheapOrderId = 'pporder_cheap_' . $suffix;
    $gateway->setCapture($cheapOrderId, $cheapSale->getTotal());

    expect('1. confirmar venta cara con order_id barato lanza DomainException',
        expectsMismatch($confirm, $customerId, (int) $expensiveSale->getSaleId(), $cheapOrderId));
    expect('1. la venta cara sigue pending',
        $saleRepository->findSaleById((int) $expensiveSale->getSaleId())?->getPaymentStatus() === 'pending');
    expect('1. no se descuenta stock del producto caro (sigue 10)', stockOf($pExpensive) === 10);

    // ----------------------------------------------------------------------
    // 2. Confirmación legítima de la venta cara con su propio order_id.
    // ----------------------------------------------------------------------
    $expensiveOrderId = 'pporder_expensive_' . $suffix;
    $gateway->setCapture($expensiveOrderId, $expensiveSale->getTotal());

    $result = $confirm->execute(
        customerId: $customerId,
        saleId: (int) $expensiveSale->getSaleId(),
        orderId: $expensiveOrderId,
        providerId: 'paypal',
        deductStock: true,
    );

    expect('2. confirm legítimo devuelve paid', $result['status'] === 'paid');
    expect('2. la venta cara promovida a paid',
        $saleRepository->findSaleById((int) $expensiveSale->getSaleId())?->getPaymentStatus() === 'paid');
    expect('2. stock descontado una vez (10 -> 8)', stockOf($pExpensive) === 8);

    // ----------------------------------------------------------------------
    // 3. Monto correcto pero moneda distinta: también se rechaza.
    // ----------------------------------------------------------------------
    $usdOrderId = 'pporder_usd_' . $suffix;
    $gateway->setCapture($usdOrderId, $cheapSale->getTotal(), 'USD');

    expect('3. moneda distinta lanza DomainException',
        expectsMismatch($confirm, $customerId, (int) $cheapSale->getSaleId(), $usdOrderId));
    expect('3. la venta barata sigue pending',
        $saleRepository->findSaleById((int) $cheapSale->getSaleId())?->getPaymentStatus() === 'pending');

    cleanup('am_' . $suffix);
} finally {
    cleanup('am_' . $suffix);
    unset($_ENV['PAYPAL_CURRENCY']);
    unset($_ENV['CONEKTA_CURRENCY']);
}

if ($GLOBALS['failed'] > 0) {
    echo "\n=== FALLARON {$GLOBALS['failed']} verificaciones ===\n";
    exit(1);
}

echo "\n=== CONFIRM MONTO/MONEDA (hallazgo ALTO auditoría) OK ===\n";