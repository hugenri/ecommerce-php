<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Container;
use App\Core\Http\Response;
use App\Http\Request;
use App\Modules\Checkout\Application\PlaceOrderUseCase;
use App\Modules\Checkout\Domain\PaymentGatewayInterface;
use App\Modules\Checkout\Domain\PaymentCapture;
use App\Modules\Checkout\Domain\PaymentCheckout;
use App\Modules\Checkout\Domain\PaymentOrder;
use App\Modules\Checkout\Domain\SaleRepositoryInterface;
use App\Modules\Checkout\Infrastructure\Conekta\ConektaGateway;
use App\Modules\Checkout\Presentation\Controllers\CheckoutController;
use App\Framework\Session\SessionManagerInterface;

/**
 * Contrato HTTP del endpoint /pago/{sale_code}/confirm a nivel controlador:
 * la respuesta SIEMPRE es el JSON consistente con status explícito y su
 * código HTTP, sin excepciones filtradas al cliente y sin que el monto
 * "Pago recibido" se muestre ante un fallo (seguridad de la auditoría).
 *
 *   paid    -> HTTP 200 { success:true,  status:"paid",    statusKey en data, referencia, payment_method, redirect_url }
 *   pending -> HTTP 200 { success:true,  status:"pending", ... }
 *   failed  -> HTTP 400/404/409/401 { success:false, status:"failed", message seguro, data con status:"failed" }
 *
 * Además el endpooint limpia la orden de pago de la sesión al confirmar.
 */

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

date_default_timezone_set('America/Mexico_City');

$_ENV['PAYPAL_CURRENCY'] = 'MXN';
$_ENV['CONEKTA_CURRENCY'] = 'MXN';

// ---------------------------------------------------------------------------
// Gateway Conekta falso: sin red. getOrder devuelve lo configurado por orderId.
// ---------------------------------------------------------------------------
final class FakeContractGateway implements PaymentGatewayInterface
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
// Request con order_id mutable (el controller se construye una sola vez per
// sleep, pero la variante de order_id cambia por escenario).
// ---------------------------------------------------------------------------
final class MutableRequest extends Request
{
    /** @var array<string, mixed> valores inyectados por el test */
    private array $overrides = [];

    public function __construct(?array $post = null)
    {
        parent::__construct(null, null, $post ?? []);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->overrides[$key] ?? $default;
    }

    public function setInput(string $key, mixed $value): void
    {
        $this->overrides[$key] = $value;
    }
}

// ---------------------------------------------------------------------------
// Response que captura el JSON en vez de emitirlo por header/echo.
// ---------------------------------------------------------------------------
final class CapturingResponse extends Response
{
    /** @var array<string, mixed> */
    public array $payload = [];

    public int $code = 0;

    public function json(mixed $data, int $statusCode = 200): void
    {
        $this->payload = is_array($data) ? $data : [];
        $this->code = $statusCode;
    }

    public function reset(): void
    {
        $this->payload = [];
        $this->code = 0;
    }
}

// ---------------------------------------------------------------------------
// Contenedor real de la aplicación (index.php), con las únicas excepciones
// que un test necesita: gateway falso, request mutable y response capturadora.
// ---------------------------------------------------------------------------
$container = new Container();
$container->instance(Container::class, $container);

$gateway = new FakeContractGateway();
$container->singleton(ConektaGateway::class, $gateway::class);
$container->instance(ConektaGateway::class, $gateway);

$request = new MutableRequest();
$container->instance(Request::class, $request);

$capture = new CapturingResponse();
$container->instance(Response::class, $capture);

App\Providers\AppServiceProvider::register($container);

$db = $container->make(App\Core\Database\Database::class);
$GLOBALS['db'] = $db;

$session = $container->make(SessionManagerInterface::class);

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
        'name' => 'Producto Test Contrato ' . $code,
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
        'last_name_paternal' => 'Contrato',
        'email' => 'conekta_ct_' . $code . '_' . $GLOBALS['suffix'] . '@example.com',
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

function cleanupContract(string $tag): void
{
    $db = $GLOBALS['db'];
    $suffix = $GLOBALS['suffix'];

    $products = [];
    foreach (['CT1_' . $suffix] as $code) {
        $prod = $db->selectOne('SELECT product_id AS id FROM products WHERE product_code = :c', ['c' => $code]);
        if ($prod !== null) {
            $products[] = (int) $prod['id'];
        }
    }

    $cust = $db->selectOne('SELECT customer_id AS id FROM customers WHERE email = :e', ['e' => 'conekta_ct_' . $tag . '_' . $suffix . '@example.com']);
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

$GLOBALS['failed'] = 0;

function expect(string $label, bool $ok): void
{
    echo ($ok ? 'PASS' : 'FAIL') . ": {$label}\n";
    if (!$ok) {
        $GLOBALS['failed']++;
    }
}

$controller = $container->make(CheckoutController::class);
$placeOrder = $container->make(PlaceOrderUseCase::class);
$saleRepository = $container->make(SaleRepositoryInterface::class);

try {
    $p1 = newProduct('CT1_' . $suffix, 100.00, 30);
    $customerA = newCustomer('a_' . $suffix);
    $addressA = newAddress($customerA);

    $saleA = $placeOrder->execute(
        customerId: $customerA,
        addressId: $addressA,
        paymentMethod: 'conekta',
        paymentStatus: 'pending',
        cartItems: [['product_id' => $p1, 'quantity' => 1]],
    );
    $saleB = $placeOrder->execute(
        customerId: $customerA,
        addressId: $addressA,
        paymentMethod: 'conekta',
        paymentStatus: 'pending',
        cartItems: [['product_id' => $p1, 'quantity' => 1]],
    );
    $saleC = $placeOrder->execute(
        customerId: $customerA,
        addressId: $addressA,
        paymentMethod: 'conekta',
        paymentStatus: 'pending',
        cartItems: [['product_id' => $p1, 'quantity' => 1]],
    );
    $saleD = $placeOrder->execute(
        customerId: $customerA,
        addressId: $addressA,
        paymentMethod: 'conekta',
        paymentStatus: 'pending',
        cartItems: [['product_id' => $p1, 'quantity' => 1]],
    );

    $saleAId = (int) $saleA->getSaleId();
    $saleBId = (int) $saleB->getSaleId();
    $saleCId = (int) $saleC->getSaleId();
    $saleDId = (int) $saleD->getSaleId();
    $total = $saleA->getTotal();

    $tag = 'a_' . $suffix;
    $orders = [];

    // --- Escenario 1: tarjeta pagada -> HTTP 200 paid -------------------------
    $orders['A'] = 'ck_ct_A_' . $suffix;
    $gateway->setOrder($orders['A'], 'paid', $total, 'MXN', null, 'card');
    $session->set('customer', ['customer_id' => $customerA]);
    $session->set('checkout.pago_conekta_order', $orders['A']);
    $request->setInput('order_id', $orders['A']);

    $controller->pagoConfirmConektaPayment($saleA->getSaleCode());

    expect('1. tarjeta pagada responde HTTP 200', $capture->code === 200);
    expect('1. contrato con success=true', $capture->payload['success'] === true);
    expect('1. contrato con status=paid', $capture->payload['status'] === 'paid');
    expect('1. mensaje = Pago recibido.', $capture->payload['message'] === 'Pago recibido.');
    expect('1. errors=null', $capture->payload['errors'] === null);
    expect('1. meta=[]', $capture->payload['meta'] === []);
    expect('1. data.status=paid', $capture->payload['data']['status'] === 'paid');
    expect('1. data.sale_id correcto', $capture->payload['data']['sale_id'] === $saleAId);
    expect('1. data.sale_code correcto', $capture->payload['data']['sale_code'] === $saleA->getSaleCode());
    expect('1. data.payment_method=card', $capture->payload['data']['payment_method'] === 'card');
    expect('1. data.redirect_url=/checkout/confirmation/{id}', $capture->payload['data']['redirect_url'] === '/checkout/confirmation/' . $saleAId);
    expect('1. data.reference=null', $capture->payload['data']['reference'] === null);
    expect('1. la orden de pago se limpia de la sesión', $session->has('checkout.pago_conekta_order') === false);
    expect('1. la venta quedó paid en BD', $saleRepository->findSaleById($saleAId)?->getPaymentStatus() === 'paid');
    $capture->reset();

    // --- Escenario 2: OXXO pendiente -> HTTP 200 pending ----------------------
    $orders['B'] = 'ck_ct_B_' . $suffix;
    $gateway->setOrder($orders['B'], 'pending', $total, 'MXN', 'OXXO-REF-' . $suffix, 'cash');
    $session->set('customer', ['customer_id' => $customerA]);
    $session->set('checkout.pago_conekta_order', $orders['B']);
    $request->setInput('order_id', $orders['B']);

    $controller->pagoConfirmConektaPayment($saleB->getSaleCode());

    expect('2. OXXO responde HTTP 200', $capture->code === 200);
    expect('2. contrato con success=true', $capture->payload['success'] === true);
    expect('2. contrato con status=pending', $capture->payload['status'] === 'pending');
    expect('2. mensaje = pendiente de confirmación', $capture->payload['message'] === 'El pago aún está pendiente de confirmación.');
    expect('2. data.reference visible', $capture->payload['data']['reference'] === 'OXXO-REF-' . $suffix);
    expect('2. data.payment_method=cash', $capture->payload['data']['payment_method'] === 'cash');
    expect('2. la venta sigue pending en BD', $saleRepository->findSaleById($saleBId)?->getPaymentStatus() === 'pending');
    $capture->reset();

    // --- Escenario 3: estado terminal Conekta (expired) -> HTTP 409 failed ----
    $orders['C'] = 'ck_ct_C_' . $suffix;
    $gateway->setOrder($orders['C'], 'expired', $total);
    $session->set('customer', ['customer_id' => $customerA]);
    $request->setInput('order_id', $orders['C']);

    $controller->pagoConfirmConektaPayment($saleC->getSaleCode());

    expect('3. conekta expirado responde HTTP 409', $capture->code === 409);
    expect('3. contrato con success=false', $capture->payload['success'] === false);
    expect('3. contrato con status=failed', $capture->payload['status'] === 'failed');
    expect('3. mensaje seguro por defecto', $capture->payload['message'] === 'El pago fue rechazado o no pudo confirmarse.');
    expect('3. NUNCA se responde Pago recibido en failed', $capture->payload['message'] !== 'Pago recibido.');
    expect('3. data.status=failed', $capture->payload['data']['status'] === 'failed');
    expect('3. data.sale_id correcto', $capture->payload['data']['sale_id'] === $saleCId);
    expect('3. data.reference=null en failed', $capture->payload['data']['reference'] === null);
    expect('3. data.redirect_url apunta a la confirmación', $capture->payload['data']['redirect_url'] === '/checkout/confirmation/' . $saleCId);
    expect('3. la venta sigue pending en BD', $saleRepository->findSaleById($saleCId)?->getPaymentStatus() === 'pending');
    $capture->reset();

    // --- Escenario 4: monto que no corresponde -> HTTP 400 failed -------------
    $orders['D'] = 'ck_ct_D_' . $suffix;
    $gateway->setOrder($orders['D'], 'paid', $total + 10.0);
    $session->set('customer', ['customer_id' => $customerA]);
    $request->setInput('order_id', $orders['D']);

    $controller->pagoConfirmConektaPayment($saleD->getSaleCode());

    expect('4. monto distinto responde HTTP 400', $capture->code === 400);
    expect('4. contrato con status=failed', $capture->payload['status'] === 'failed');
    expect('4. success=false', $capture->payload['success'] === false);
    expect('4. NUNCA se responde Pago recibido en failed', $capture->payload['message'] !== 'Pago recibido.');
    expect('4. la venta sigue pending en BD', $saleRepository->findSaleById($saleDId)?->getPaymentStatus() === 'pending');
    $capture->reset();

    // --- Escenario 5: sin sesión -> HTTP 401 failed ---------------------------
    $session->remove('customer');
    $request->setInput('order_id', $orders['A']);

    $controller->pagoConfirmConektaPayment($saleA->getSaleCode());

    expect('5. sin sesión responde HTTP 401', $capture->code === 401);
    expect('5. status=failed', $capture->payload['status'] === 'failed');
    expect('5. mensaje de sesión', $capture->payload['message'] === 'Inicia sesión para continuar.');
    expect('5. data.sale_id=null', $capture->payload['data']['sale_id'] === null);
    expect('5. data.redirect_url=/', $capture->payload['data']['redirect_url'] === '/');
    $capture->reset();

    // --- Escenario 6: venta de otro cliente -> HTTP 404 failed ----------------
    $session->set('customer', ['customer_id' => 99999999]);
    $request->setInput('order_id', $orders['A']);

    $controller->pagoConfirmConektaPayment($saleA->getSaleCode());

    expect('6. venta no del cliente responde HTTP 404', $capture->code === 404);
    expect('6. status=failed', $capture->payload['status'] === 'failed');
    expect('6. mensaje pedido no encontrado', $capture->payload['message'] === 'Pedido no encontrado.');
    expect('6. data.sale_id=null', $capture->payload['data']['sale_id'] === null);
    $capture->reset();

    // --- Escenario 7: sin order_id -> HTTP 400 failed -------------------------
    $session->set('customer', ['customer_id' => $customerA]);
    $request->setInput('order_id', '');

    $controller->pagoConfirmConektaPayment($saleA->getSaleCode());

    expect('7. sin order_id responde HTTP 400', $capture->code === 400);
    expect('7. status=failed', $capture->payload['status'] === 'failed');
    expect('7. mensaje solicitud inválida', $capture->payload['message'] === 'Solicitud inválida.');
    expect('7. data.sale_id se conserva (venta encontrada)', $capture->payload['data']['sale_id'] === $saleAId);
    $capture->reset();

    cleanupContract($tag);
} finally {
    cleanupContract('a_' . $suffix);
    $session->clear();
    unset($_ENV['PAYPAL_CURRENCY']);
    unset($_ENV['CONEKTA_CURRENCY']);
}

if ($GLOBALS['failed'] > 0) {
    echo "\n=== FALLARON {$GLOBALS['failed']} verificaciones ===\n";
    exit(1);
}

echo "\n=== CONTRATO HTTP /pago/{sale_code}/confirm (paid/pending/failed) OK ===\n";