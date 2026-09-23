<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Container;
use App\Core\Database\Database;
use App\Modules\Inventory\Domain\InventoryRepositoryInterface;
use App\Modules\Inventory\Persistence\InventoryRepository;
use App\Modules\Checkout\Domain\SaleRepositoryInterface;
use App\Modules\Checkout\Persistence\SaleRepository;
use App\Modules\Checkout\Domain\PaymentTransactionRepositoryInterface;
use App\Modules\Checkout\Persistence\PaymentTransactionRepository;
use App\Modules\Checkout\Domain\PaymentGatewayInterface;
use App\Modules\Checkout\Domain\PaymentCapture;
use App\Modules\Checkout\Domain\PaymentOrder;
use App\Modules\Checkout\Domain\PaymentCheckout;
use App\Modules\Products\Domain\ProductRepositoryInterface;
use App\Modules\Products\Persistence\ProductRepository;
use App\Modules\Checkout\Application\UseCases\HandleConektaWebhookUseCase;
use App\Modules\Checkout\Infrastructure\Conekta\ConektaGateway;

/**
 * Test de regresión del descuento diferido de stock en el webhook Conekta
 * (pending -> paid), introducido en la separación Checkout/Pago.
 *
 * Cubre:
 *   1. Venta materializada directo como 'paid' (tarjeta): descuenta una sola vez
 *      y es idempotente frente a reintentos del mismo evento.
 *   2. Venta 'pending' (OXXO) que pasa a 'paid' vía webhook: descuenta una sola
 *      vez y es idempotente frente a duplicados.
 *   3. Venta 'pending' -> 'paid' sin stock suficiente: se marca conflicto
 *      (cancelled + failed + nota) y NO descuenta ni falla sin capturar.
 *
 * El gateway Conekta es un doble registrado en el contenedor bajo el nombre de
 * la clase real (ConektaGateway), así el PaymentGatewayResolver real lo
 * resuelve sin red. La firma RSA se genera con el runtime de Node (disponible
 * en el entorno de desarrollo) porque el OpenSSL de este PHP (fork 8.2) no
 * puede firmar (openssl_sign/openssl_pkey_new no funcionan); sí puede
 * verificar.
 *
 * Las claves RSA aquí son EXCLUSIVAMENTE de prueba: se generan al azar en cada
 * ejecución por Node y no tienen relación con credenciales reales de Conekta.
 * La pública de prueba se inyecta en CONEKTA_WEBHOOK_PUBLIC_KEY dentro de
 * ESTE proceso (aislado por proceso; no se lee ni sobrescribe el .env) y se
 * restaura al salir.
 */

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// ---------------------------------------------------------------------------
// Preparación (una sola vez) de un par RSA de prueba mediante Node.
// Node genera el par, exporta las claves a archivos temporales y PHP lee la
// pública para inyectarla en el entorno (solo este proceso).
// ---------------------------------------------------------------------------
$testTmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ckwebhook_' . bin2hex(random_bytes(4));
mkdir($testTmp);

$keyGenScript = $testTmp . DIRECTORY_SEPARATOR . 'genkey.mjs';
file_put_contents($keyGenScript,
    'import fs from "node:fs";'
    . 'import c from "node:crypto";'
    . 'const k = c.generateKeyPairSync("rsa", { modulusLength: 2048 });'
    . 'const priv = await k.privateKey.export({ format: "pem", type: "pkcs8" });'
    . 'const pub = await k.publicKey.export({ format: "pem", type: "spki" });'
    . 'fs.writeFileSync(process.argv[2], priv);'
    . 'fs.writeFileSync(process.argv[3], pub);');

$scriptPath = $testTmp . DIRECTORY_SEPARATOR . 'ckwebhook_sign.mjs';
$privKeyPath = $testTmp . DIRECTORY_SEPARATOR . 'test_key.pem';
$pubKeyPath = $testTmp . DIRECTORY_SEPARATOR . 'test_key.pub.pem';

$genExit = shell_exec('node ' . escapeshellarg($keyGenScript)
    . ' ' . escapeshellarg($privKeyPath)
    . ' ' . escapeshellarg($pubKeyPath));

if (!file_exists($pubKeyPath)) {
    echo "FAIL: no se pudo generar el par RSA de prueba con Node.\n";
    exit(1);
}

$testPublicKeyPem = trim(file_get_contents($pubKeyPath));

file_put_contents($scriptPath,
    'import fs from "node:fs";'
    . 'import c from "node:crypto";'
    . 'import path from "node:path";'
    . 'const key = c.createPrivateKey(fs.readFileSync(process.argv[3], "utf8"));'
    . 'const data = fs.readFileSync(process.argv[2], "utf8");'
    . 'const sig = await c.sign("sha256", data, key);'
    . 'process.stdout.write(Buffer.from(sig).toString("base64"));');

// Publica de prueba inyectada solo en este proceso (no toca el .env).
$_ENV['CONEKTA_WEBHOOK_PUBLIC_KEY'] = $testPublicKeyPem;
$_ENV['CONEKTA_CURRENCY'] = 'MXN';

$GLOBALS['signScriptPath'] = $scriptPath;
$GLOBALS['signKeyPath'] = $privKeyPath;
$GLOBALS['testTmp'] = $testTmp;

/**
 * Firma el cuerpo del webhook con la clave privada de prueba usando Node.
 *
 * Devuelve el header dispuesto para verifySignature(): "sha256=<b64>".
 */
function signBody(string $rawBody): string
{
    $bodyFile = $GLOBALS['testTmp'] . DIRECTORY_SEPARATOR . 'body_' . uniqid() . '.json';

    file_put_contents($bodyFile, $rawBody);

    $cmd = 'node ' . escapeshellarg($GLOBALS['signScriptPath'])
        . ' ' . escapeshellarg($bodyFile)
        . ' ' . escapeshellarg($GLOBALS['signKeyPath']);

    $out = shell_exec($cmd);

    @unlink($bodyFile);

    $sigB64 = trim((string) $out);

    if ($sigB64 === '') {
        throw new \RuntimeException('No se pudo firmar el cuerpo del webhook (node).');
    }

    return 'sha256=' . $sigB64;
}

// ---------------------------------------------------------------------------
// Gateway falso de Conekta: sin red. El estado de la orden es mutable para
// simular la secuencia pending (OXXO) -> paid en distintas notificaciones.
// ---------------------------------------------------------------------------
final class FakeConektaGateway implements PaymentGatewayInterface
{
    /** @var array<string, string> orderId -> status de captura */
    private array $statuses = [];

    private const AMOUNT = 300.0;

    private const CURRENCY = 'MXN';

    public function setStatus(string $orderId, string $status): void
    {
        $this->statuses[$orderId] = $status;
    }

    public function createOrder(PaymentOrder $order): string
    {
        return $order->getOrderId();
    }

    public function createRedirectCheckout(PaymentOrder $order): PaymentCheckout
    {
        throw new \RuntimeException('No usado en el test.');
    }

    public function captureOrder(string $orderId): PaymentCapture
    {
        throw new \RuntimeException('Conekta no captura server-to-server.');
    }

    public function getOrder(string $orderId): PaymentCapture
    {
        $status = $this->statuses[$orderId] ?? 'paid';

        return new PaymentCapture(
            orderId: $orderId,
            status: $status,
            amount: self::AMOUNT,
            currency: self::CURRENCY,
            captureId: 'cap_' . $orderId,
        );
    }

    public function classifyEvent(string $eventType): ?string
    {
        return match ($eventType) {
            'charge.created' => self::EVENT_CHARGE_CREATED,
            'order.paid', 'charge.paid' => self::EVENT_PAID,
            'order.declined', 'charge.declined' => self::EVENT_FAILED,
            'order.canceled', 'charge.canceled', 'order.voided' => self::EVENT_CANCELLED,
            'order.expired', 'charge.expired' => self::EVENT_EXPIRED,
            default => null,
        };
    }
}

// ---------------------------------------------------------------------------
// Contenedor y repositorios.
// ---------------------------------------------------------------------------
$container = new Container();
$container->instance(Container::class, $container);
$container->singleton(Database::class);
$container->bind(InventoryRepositoryInterface::class, InventoryRepository::class);
$container->bind(SaleRepositoryInterface::class, SaleRepository::class);
$container->bind(PaymentTransactionRepositoryInterface::class, PaymentTransactionRepository::class);
$container->bind(ProductRepositoryInterface::class, ProductRepository::class);
$container->singleton(ConektaGateway::class, FakeConektaGateway::class);

$db = $container->make(Database::class);
$GLOBALS['db'] = $db;

$suffix = bin2hex(random_bytes(4));
$GLOBALS['suffix'] = $suffix;
$subcategory = $GLOBALS['db']->selectOne("SELECT subcategory_id FROM subcategories LIMIT 1");
if (!$subcategory) {
    echo "FAIL: no hay subcategorias disponibles\n";
    exit(1);
}
$subcategoryId = (int) $subcategory['subcategory_id'];
$GLOBALS['subcategoryId'] = $subcategoryId;

function newProduct(string $code, int $stock): int
{
    $id = (int) $GLOBALS['db']->insert('products', [
        'subcategory_id' => $GLOBALS['subcategoryId'],
        'product_code' => $code,
        'name' => 'Producto Test Webhook ' . $code,
        'stock' => $stock,
        'price' => 100.00,
        'discount' => 0.00,
        'status' => 'active',
    ]);
    return $id;
}

function newCustomer(string $code): int
{
    return (int) $GLOBALS['db']->insert('customers', [
        'first_name' => 'Cliente',
        'last_name_paternal' => 'Webhook',
        'email' => 'webhook_' . $code . '@example.com',
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
    $orderPrefix = 'test_' . $suffix . '_' . $tag;
    $productCode = strtoupper($tag) . '_' . $suffix;
    $email = 'webhook_' . $tag . '_' . $suffix . '@example.com';

    $saleIds = [];
    foreach ($db->query('SELECT sale_id AS sid FROM payment_transactions WHERE provider_order_id LIKE :p', ['p' => $orderPrefix . '%']) as $row) {
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

    // Producto de este caso (por código exacto).
    $prod = $db->selectOne('SELECT product_id AS id FROM products WHERE product_code = :c', ['c' => $productCode]);
    if ($prod !== null) {
        $pid = (int) $prod['id'];
        $db->query('DELETE FROM inventory_movements WHERE product_id = :id', ['id' => $pid]);
        $db->query('DELETE FROM sale_details WHERE product_id = :id', ['id' => $pid]);
        $db->query('DELETE FROM products WHERE product_id = :id', ['id' => $pid]);
    }

    // Cliente (y sus direcciones) de este caso.
    $cust = $db->selectOne('SELECT customer_id AS id FROM customers WHERE email = :e', ['e' => $email]);
    if ($cust !== null) {
        $cid = (int) $cust['id'];
        $db->query('DELETE FROM addresses WHERE customer_id = :id', ['id' => $cid]);
        $db->query('DELETE FROM customers WHERE customer_id = :id', ['id' => $cid]);
    }

    $db->query("DELETE FROM payment_transactions WHERE provider_order_id LIKE :p", ['p' => $orderPrefix . '%']);
}

$gateway = $container->make(ConektaGateway::class);
$useCase = new HandleConektaWebhookUseCase(
    gatewayResolver: $container->make(App\Modules\Checkout\Application\Services\PaymentGatewayResolver::class),
    transactionRepository: $container->make(PaymentTransactionRepositoryInterface::class),
    saleRepository: $container->make(SaleRepositoryInterface::class),
    placeOrder: $container->make(App\Modules\Checkout\Application\PlaceOrderUseCase::class),
    cancelSale: $container->make(App\Modules\Checkout\Application\Admin\CancelSaleUseCase::class),
    inventoryRepository: $container->make(InventoryRepositoryInterface::class),
    conektaConfig: $container->make(App\Config\ConektaConfig::class),
    paymentAmountValidator: $container->make(App\Modules\Checkout\Domain\PaymentAmountValidator::class),
);
$GLOBALS['useCase'] = $useCase;

/**
 * @param string $eventType type del evento en el payload
 * @param string $orderId   id de la orden
 * @param int    $customerId
 * @param int    $addressId
 * @param array<int, array<string, mixed>> $items items [{id, qty}]
 */
function sendWebhook(string $eventType, string $orderId, int $customerId, int $addressId, array $items): void
{
    $payload = [
        'type' => $eventType,
        'data' => [
            'object' => [
                'id' => $orderId,
                'order_id' => $orderId,
                'metadata' => [
                    'customer_id' => $customerId,
                    'address_id' => $addressId,
                    'items' => $items,
                ],
            ],
        ],
    ];
    $rawBody = json_encode($payload);

    $GLOBALS['useCase']->execute(
        rawBody: $rawBody,
        digestHeader: signBody($rawBody),
        payload: $payload,
    );
}

function stockOf(int $productId): int
{
    return (int) $GLOBALS['db']->selectOne('SELECT stock FROM products WHERE product_id = :id', ['id' => $productId])['stock'];
}

function saleCodeByOrder(string $orderId): ?string
{
    $row = $GLOBALS['db']->selectOne(
        'SELECT s.sale_code AS code FROM sales s JOIN payment_transactions t ON t.sale_id = s.sale_id WHERE t.provider_order_id = :oid LIMIT 1',
        ['oid' => $orderId]
    );

    return $row === null ? null : (string) ($row['code'] ?? '');
}

function paymentStatusBySale(string $saleCode): ?string
{
    $row = $GLOBALS['db']->selectOne('SELECT payment_status AS p FROM sales WHERE sale_code = :c', ['c' => $saleCode]);
    return $row === null ? null : (string) ($row['p'] ?? '');
}

function saleStatusBySale(string $saleCode): ?string
{
    $row = $GLOBALS['db']->selectOne('SELECT status AS s FROM sales WHERE sale_code = :c', ['c' => $saleCode]);
    return $row === null ? null : (string) ($row['s'] ?? '');
}

function saleNotesBySale(string $saleCode): ?string
{
    $row = $GLOBALS['db']->selectOne('SELECT notes AS n FROM sales WHERE sale_code = :c', ['c' => $saleCode]);
    return $row === null ? null : (string) ($row['n'] ?? '');
}

function movementCount(int $saleId): int
{
    return (int) $GLOBALS['db']->selectOne(
        "SELECT COUNT(*) AS total FROM inventory_movements WHERE origin_type = 'sale' AND origin_id = :sid",
        ['sid' => $saleId]
    )['total'];
}

$GLOBALS['failed'] = 0;

function expect(string $label, bool $ok): void
{
    echo ($ok ? 'PASS' : 'FAIL') . ": {$label}\n";
    if (!$ok) {
        $GLOBALS['failed']++;
    }
}

try {
    // ========================================================================
    // CASO 3: OXXO (pending) -> paid pero el stock se agotó entre la reserva y
    // el pago (otra venta consumió el stock). Se marca conflicto y NO descuenta.
    // ========================================================================
    $pLow = newProduct('LOW_' . $suffix, 3);
    $cLow = newCustomer('low_' . $suffix);
    $aLow = newAddress($cLow);
    $lowOrder = 'test_' . $suffix . '_low';

    // 3a. OXXO crea la venta pendiente (stock suficiente en ese momento: 3).
    $gateway->setStatus($lowOrder, 'pending_payment');
    sendWebhook('charge.created', $lowOrder, $cLow, $aLow, [['id' => $pLow, 'qty' => 3]]);
    $lowCode = saleCodeByOrder($lowOrder);

    expect('3a. venta creada como pending', $lowCode !== null && paymentStatusBySale($lowCode) === 'pending');
    expect('3a. pending NO descuenta (sigue 3)', stockOf($pLow) === 3);

    // 3b. Simula que otra venta se llevó el stock antes del pago (queda 1).
    $GLOBALS['db']->update(
        'products',
        ['stock' => 1],
        ['product_id' => $pLow]
    );

    // 3c. order.paid sin stock suficiente: conflicto visible, sin 500.
    $gateway->setStatus($lowOrder, 'paid');
    sendWebhook('order.paid', $lowOrder, $cLow, $aLow, [['id' => $pLow, 'qty' => 3]]);

    expect('3c. conflicto visible (cancelled + failed + nota)',
        saleStatusBySale($lowCode) === 'cancelled'
        && paymentStatusBySale($lowCode) === 'failed'
        && str_contains(saleNotesBySale($lowCode) ?? '', 'CONFLICTO_STOCK'));

    expect('3c. sin movimiento de sale (no se descuenta)',
        movementCount((int) $GLOBALS['db']->selectOne('SELECT sale_id AS id FROM sales WHERE sale_code = :c', ['c' => $lowCode])['id']) === 0);

    expect('3c. stock queda como estaba (1, no se descuenta)', stockOf($pLow) === 1);

    cleanup('low');

    // ========================================================================
    // CASO 1: venta materializada directo como paid (tarjeta). Descuenta una sola vez.
    // ========================================================================
    $p1 = newProduct('P1_' . $suffix, 10);
    $c1 = newCustomer('p1_' . $suffix);
    $a1 = newAddress($c1);
    $order1 = 'test_' . $suffix . '_p1';
    $gateway->setStatus($order1, 'paid');

    sendWebhook('order.paid', $order1, $c1, $a1, [['id' => $p1, 'qty' => 3]]);
    $code1 = saleCodeByOrder($order1);

    expect('1. venta creada como paid', paymentStatusBySale($code1) === 'paid');
    expect('1. stock descontado una vez (10 -> 7)', stockOf($p1) === 7);

    $saleId1 = (int) $GLOBALS['db']->selectOne('SELECT sale_id AS id FROM sales WHERE sale_code = :c', ['c' => $code1])['id'];
    expect('1. exactamente un movimiento de sale', movementCount($saleId1) === 1);

    // Reintento del mismo evento pagado: idempotente, no vuelve a descontar.
    sendWebhook('order.paid', $order1, $c1, $a1, [['id' => $p1, 'qty' => 3]]);
    expect('1. reintento no duplica el descuento (sigue 7)', stockOf($p1) === 7);
    expect('1. reintento no duplica el movimiento', movementCount($saleId1) === 1);

    cleanup('p1');

    // ========================================================================
    // CASO 2: OXXO (pending) -> paid vía webhook. Descuenta una sola vez.
    // ========================================================================
    $p2 = newProduct('P2_' . $suffix, 10);
    $c2 = newCustomer('p2_' . $suffix);
    $a2 = newAddress($c2);
    $order2 = 'test_' . $suffix . '_p2';

    // 2a. charge.created con orden aun en pending_payment (OXXO): NO descuenta.
    $gateway->setStatus($order2, 'pending_payment');
    sendWebhook('charge.created', $order2, $c2, $a2, [['id' => $p2, 'qty' => 3]]);
    $code2 = saleCodeByOrder($order2);

    expect('2a. venta creada como pending', paymentStatusBySale($code2) === 'pending');
    expect('2a. pending NO descuenta stock (sigue 10)', stockOf($p2) === 10);

    $saleId2 = (int) $GLOBALS['db']->selectOne('SELECT sale_id AS id FROM sales WHERE sale_code = :c', ['c' => $code2])['id'];
    expect('2a. sin movimientos de sale en pending', movementCount($saleId2) === 0);

    // 2b. order.paid sobre la misma orden pending: descuenta una vez.
    $gateway->setStatus($order2, 'paid');
    sendWebhook('order.paid', $order2, $c2, $a2, [['id' => $p2, 'qty' => 3]]);

    expect('2b. pasa a paid', paymentStatusBySale($code2) === 'paid');
    expect('2b. descuenta stock una vez (10 -> 7)', stockOf($p2) === 7);
    expect('2b. exactamente un movimiento de sale', movementCount($saleId2) === 1);

    // 2c. Duplicado del mismo order.paid: no vuelve a descontar.
    sendWebhook('order.paid', $order2, $c2, $a2, [['id' => $p2, 'qty' => 3]]);
    expect('2c. duplicado no vuelve a descontar (sigue 7)', stockOf($p2) === 7);
    expect('2c. duplicado no agrega movimientos', movementCount($saleId2) === 1);

    cleanup('p2');
} finally {
    cleanup('low');
    cleanup('p1');
    cleanup('p2');
    unset($_ENV['CONEKTA_WEBHOOK_PUBLIC_KEY']);
    unset($_ENV['CONEKTA_CURRENCY']);

    $tmp = $GLOBALS['testTmp'] ?? null;
    if ($tmp !== null && file_exists($tmp)) {
        foreach (glob($tmp . DIRECTORY_SEPARATOR . '*') as $f) {
            @unlink($f);
        }
        @rmdir($tmp);
    }
}

if ($GLOBALS['failed'] > 0) {
    echo "\n=== FALLARON {$GLOBALS['failed']} verificaciones ===\n";
    exit(1);
}

echo "\n=== INTEGRACION WEBHOOK CONEKTA (pago diferido) OK ===\n";