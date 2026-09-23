<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Router;
use App\Core\Container;
use App\Core\Database\Database;
use App\Modules\Checkout\Domain\SaleRepositoryInterface;
use App\Modules\Checkout\Persistence\SaleRepository;
use App\Modules\Checkout\Application\PlaceOrderUseCase;
use App\Modules\Products\Domain\ProductRepositoryInterface;
use App\Modules\Products\Persistence\ProductRepository;
use App\Modules\Inventory\Domain\InventoryRepositoryInterface;
use App\Modules\Inventory\Persistence\InventoryRepository;
use App\Modules\Checkout\Presentation\Controllers\CheckoutController;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// ─── 1) Test de rutas: el nuevo flujo /pago y los alias de Conekta ──────────
$router = new Router();
require __DIR__ . '/../routes/routes.php';

$routes = $router->getRoutes();

function routeMatches(array $routes, string $method, string $uri, string $class, string $methodName): string
{
    foreach ($routes as $route) {
        if (
            $route['method'] === $method
            && $route['uri'] === $uri
            && is_array($route['handler'])
            && $route['handler'][0] === $class
            && $route['handler'][1] === $methodName
        ) {
            return 'PASS';
        }
    }
    return 'FAIL';
}

echo "Ruta GET /pago/{sale_code} -> CheckoutController@pago: "
    . routeMatches($routes, 'GET', '/pago/{sale_code}', CheckoutController::class, 'pago') . "\n";
echo "Ruta POST /pago/{sale_code}/create-order -> pagoCreateConektaOrder: "
    . routeMatches($routes, 'POST', '/pago/{sale_code}/create-order', CheckoutController::class, 'pagoCreateConektaOrder') . "\n";
echo "Ruta POST /pago/{sale_code}/confirm -> pagoConfirmConektaPayment: "
    . routeMatches($routes, 'POST', '/pago/{sale_code}/confirm', CheckoutController::class, 'pagoConfirmConektaPayment') . "\n";
echo "Ruta POST /checkout/conekta/place-order -> placeConektaOrder (nuevo boton Pagar pedido): "
    . routeMatches($routes, 'POST', '/checkout/conekta/place-order', CheckoutController::class, 'placeConektaOrder') . "\n";
echo "Ruta POST /checkout/paypal/place-order -> placePaypalOrder (nuevo boton Pagar pedido PayPal): "
    . routeMatches($routes, 'POST', '/checkout/paypal/place-order', CheckoutController::class, 'placePaypalOrder') . "\n";

function routeRemoved(array $routes, string $method, string $uri): string
{
    foreach ($routes as $route) {
        if ($route['method'] === $method && $route['uri'] === $uri) {
            return 'FAIL'; // el endpoint muerto no debería estar registrado
        }
    }
    return 'PASS';
}

echo "Limpiado: ruta antigua POST /checkout/conekta/create-order removida: "
    . routeRemoved($routes, 'POST', '/checkout/conekta/create-order') . "\n";
echo "Limpiado: ruta antigua POST /checkout/conekta/confirm removida: "
    . routeRemoved($routes, 'POST', '/checkout/conekta/confirm') . "\n";
echo "Limpiado: ruta antigua POST /checkout/paypal/create-order removida: "
    . routeRemoved($routes, 'POST', '/checkout/paypal/create-order') . "\n";
echo "Limpiado: ruta antigua POST /checkout/paypal/capture-order removida: "
    . routeRemoved($routes, 'POST', '/checkout/paypal/capture-order') . "\n";
echo "Pago sigue viviendo en GET /pago/{sale_code} -> pago: "
    . routeMatches($routes, 'GET', '/pago/{sale_code}', CheckoutController::class, 'pago') . "\n";

// ─── 2) La venta pending se crea en el checkout ANTES de redirigir a /pago ─
$container = new Container();
$container->singleton(Database::class);
$container->bind(SaleRepositoryInterface::class, SaleRepository::class);
$container->bind(ProductRepositoryInterface::class, ProductRepository::class);
$container->bind(InventoryRepositoryInterface::class, InventoryRepository::class);

$db = $container->make(Database::class);

$suffix = bin2hex(random_bytes(4));
$subcategory = $db->selectOne("SELECT subcategory_id FROM subcategories LIMIT 1");
if (!$subcategory) {
    echo "FAIL: no hay subcategorias disponibles\n";
    exit(1);
}
$subcategoryId = (int) $subcategory['subcategory_id'];

$productCode = 'PAGO_TEST_' . $suffix;
$customerEmail = 'pago_test_' . $suffix . '@example.com';

$productId = (int) $db->insert('products', [
    'subcategory_id' => $subcategoryId,
    'product_code' => $productCode,
    'name' => 'Producto Test Pago ' . $suffix,
    'stock' => 8,
    'price' => 50.00,
    'discount' => 0.00,
    'status' => 'active',
]);

$customerId = (int) $db->insert('customers', [
    'first_name' => 'Cliente',
    'last_name_paternal' => 'Pago',
    'email' => $customerEmail,
    'password' => password_hash('test', PASSWORD_BCRYPT),
    'active' => 1,
]);

$addressId = (int) $db->insert('addresses', [
    'customer_id' => $customerId,
    'street' => 'Av. Prueba',
    'number' => '456',
    'neighborhood' => 'Centro',
    'municipality' => 'Test',
    'state' => 'Test',
    'zip_code' => '00000',
    'is_default' => 1,
]);

$saleCode = null;

try {
    $placeOrder = $container->make(PlaceOrderUseCase::class);

    // "Pagar pedido": persiste la venta como pending, SIN descontar stock.
    $sale = $placeOrder->execute(
        customerId: $customerId,
        addressId: $addressId,
        paymentMethod: 'conekta',
        paymentStatus: 'pending',
        cartItems: [
            ['product_id' => $productId, 'quantity' => 2],
        ],
    );

    $saleId = (int) $sale->getSaleId();
    $saleCode = $sale->getSaleCode();

    $okSale = $saleId > 0 && $sale->getSaleCode() !== '';
    echo "Venta pending creada en el checkout (sale_id seteado): " . ($okSale ? 'PASS' : 'FAIL') . "\n";

    $stockAfter = (int) $db->selectOne('SELECT stock FROM products WHERE product_id = :id', ['id' => $productId])['stock'];
    echo "La creacion pending NO descuenta stock (8 -> 8): " . ($stockAfter === 8 ? 'PASS' : 'FAIL') . "\n";

    // El flujo /pago/{sale_code} lee de la venta persistida via findBySaleCode.
    $saleRepository = $container->make(SaleRepositoryInterface::class);
    $persisted = $saleRepository->findBySaleCode($saleCode);
    $okPersisted = $persisted
        && $persisted->getSaleId() === $saleId
        && $persisted->getCustomerId() === $customerId;
    echo "Leida en /pago via findBySaleCode (mismo dueño): " . ($okPersisted ? 'PASS' : 'FAIL') . "\n";

    $details = $saleRepository->findDetailsBySaleId($saleId);
    $okLines = count($details) === 1 && (int) $details[0]['quantity'] === 2;
    echo "Detalle de la venta pending persistido para /pago: " . ($okLines ? 'PASS' : 'FAIL') . "\n";
} finally {
    if ($saleCode) {
        $db->query("DELETE FROM inventory_movements WHERE origin_id IN (SELECT sale_id FROM sales WHERE sale_code = :code)", ['code' => $saleCode]);
        $db->query("DELETE FROM sale_details WHERE sale_id IN (SELECT sale_id FROM sales WHERE sale_code = :code)", ['code' => $saleCode]);
        $db->query("DELETE FROM deliveries WHERE sale_id IN (SELECT sale_id FROM sales WHERE sale_code = :code)", ['code' => $saleCode]);
        $db->query("DELETE FROM payment_transactions WHERE sale_id IN (SELECT sale_id FROM sales WHERE sale_code = :code)", ['code' => $saleCode]);
        $db->query("DELETE FROM sales WHERE sale_code = :code", ['code' => $saleCode]);
    }
    $db->query("DELETE FROM addresses WHERE customer_id = :id", ['id' => $customerId]);
    $db->query("DELETE FROM customers WHERE customer_id = :id", ['id' => $customerId]);
    $db->query("DELETE FROM products WHERE product_id = :id", ['id' => $productId]);
}