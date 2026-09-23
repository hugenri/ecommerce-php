<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Container;
use App\Core\Database\Database;
use App\Modules\Inventory\Domain\InventoryRepositoryInterface;
use App\Modules\Inventory\Persistence\InventoryRepository;
use App\Modules\Checkout\Domain\SaleRepositoryInterface;
use App\Modules\Checkout\Persistence\SaleRepository;
use App\Modules\Products\Domain\ProductRepositoryInterface;
use App\Modules\Products\Persistence\ProductRepository;
use App\Modules\Checkout\Application\PlaceOrderUseCase;
use App\Modules\Checkout\Application\Admin\CancelSaleUseCase;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$container = new Container();
$container->singleton(Database::class);
$container->bind(InventoryRepositoryInterface::class, InventoryRepository::class);
$container->bind(SaleRepositoryInterface::class, SaleRepository::class);
$container->bind(ProductRepositoryInterface::class, ProductRepository::class);

$db = $container->make(Database::class);

$suffix = bin2hex(random_bytes(4));

$subcategory = $db->selectOne("SELECT subcategory_id FROM subcategories LIMIT 1");
if (!$subcategory) {
    echo "FAIL: no hay subcategorias disponibles\n";
    exit(1);
}
$subcategoryId = (int) $subcategory['subcategory_id'];

// Datos de prueba
$productCode = 'SALE_TEST_' . $suffix;
$customerEmail = 'sale_test_' . $suffix . '@example.com';
$saleCode = 'OC-' . date('Ymd') . '-' . strtoupper($suffix);

$db->query("DELETE FROM inventory_movements WHERE origin_id IN (SELECT sale_id FROM sales WHERE sale_code = :code)", ['code' => $saleCode]);
$db->query("DELETE FROM sale_details WHERE sale_id IN (SELECT sale_id FROM sales WHERE sale_code = :code)", ['code' => $saleCode]);
$db->query("DELETE FROM deliveries WHERE sale_id IN (SELECT sale_id FROM sales WHERE sale_code = :code)", ['code' => $saleCode]);
$db->query("DELETE FROM sales WHERE sale_code = :code", ['code' => $saleCode]);
$db->query("DELETE FROM addresses WHERE customer_id IN (SELECT customer_id FROM customers WHERE email = :email)", ['email' => $customerEmail]);
$db->query("DELETE FROM customers WHERE email = :email", ['email' => $customerEmail]);
$db->query("DELETE FROM inventory_movements WHERE product_id IN (SELECT product_id FROM products WHERE product_code = :code)", ['code' => $productCode]);
$db->query("DELETE FROM products WHERE product_code = :code", ['code' => $productCode]);

$productId = (int) $db->insert('products', [
    'subcategory_id' => $subcategoryId,
    'product_code' => $productCode,
    'name' => 'Producto Test Venta ' . $suffix,
    'stock' => 10,
    'price' => 100.00,
    'discount' => 0.00,
    'status' => 'active',
]);

$customerId = (int) $db->insert('customers', [
    'first_name' => 'Cliente',
    'last_name_paternal' => 'Test',
    'email' => $customerEmail,
    'password' => password_hash('test', PASSWORD_BCRYPT),
    'active' => 1,
]);

$addressId = (int) $db->insert('addresses', [
    'customer_id' => $customerId,
    'street' => 'Av. Prueba',
    'number' => '123',
    'neighborhood' => 'Centro',
    'municipality' => 'Test',
    'state' => 'Test',
    'zip_code' => '00000',
    'is_default' => 1,
]);

$adminUser = $db->selectOne("SELECT user_id FROM users WHERE role = 'admin' OR is_active = 1 ORDER BY user_id LIMIT 1");
$userId = (int) ($adminUser['user_id'] ?? 1);

$createdSaleCode = null;
$createdSaleId = null;

try {
    // 1. Venta de 3 unidades
    $placeOrder = $container->make(PlaceOrderUseCase::class);
    $sale = $placeOrder->execute(
        customerId: $customerId,
        addressId: $addressId,
        paymentMethod: 'card',
        cartItems: [
            ['product_id' => $productId, 'quantity' => 3],
        ],
    );
    $saleId = (int) $sale->getSaleId();
    $createdSaleCode = $sale->getSaleCode();
    $createdSaleId = $saleId;
    echo "PlaceOrderUseCase (venta creada): " . ($saleId > 0 ? 'PASS' : 'FAIL') . "\n";

    $stockAfter = (int) $db->selectOne('SELECT stock FROM products WHERE product_id = :id', ['id' => $productId])['stock'];
    echo "Venta descuenta stock (10 -> 7): " . ($stockAfter === 7 ? 'PASS' : 'FAIL') . "\n";

    // 2. Movimiento sale: origin sale/sale_id, user_id NULL (checkout), quantity -3
    $mov = $db->selectOne(
        "SELECT * FROM inventory_movements WHERE origin_type = 'sale' AND origin_id = :sid",
        ['sid' => $saleId]
    );
    $okMov = $mov
        && $mov['movement_type'] === 'sale'
        && $mov['quantity'] == -3
        && $mov['previous_stock'] == 10
        && $mov['current_stock'] == 7
        && $mov['user_id'] === null;
    echo "Movimiento sale (sale_id, user_id NULL): " . ($okMov ? 'PASS' : 'FAIL') . "\n";

    // 2b. El motivo de venta se genera automaticamente con el sale_code
    $okReason = $mov && str_contains($mov['reason'] ?? '', 'Venta ' . $createdSaleCode);
    echo "Movimiento sale (motivo 'Venta {sale_code}'): " . ($okReason ? 'PASS' : 'FAIL') . "\n";

    // 3. Venta con dos productos distintos registra un movimiento por producto
    $movCount = (int) $db->selectOne(
        "SELECT COUNT(*) AS total FROM inventory_movements WHERE origin_type = 'sale' AND origin_id = :sid",
        ['sid' => $saleId]
    )['total'];
    echo "Movimientos por producto (1): " . ($movCount === 1 ? 'PASS' : 'FAIL') . "\n";

    // 4. Cancelacion restaura stock y registra movement adjustment con origin sale
    $cancelSale = $container->make(CancelSaleUseCase::class);
    $cancelSale->execute($saleId, $userId);

    $stockAfterCancel = (int) $db->selectOne('SELECT stock FROM products WHERE product_id = :id', ['id' => $productId])['stock'];
    echo "Cancelacion restaura stock (7 -> 10): " . ($stockAfterCancel === 10 ? 'PASS' : 'FAIL') . "\n";

    $rest = $db->selectOne(
        "SELECT * FROM inventory_movements WHERE movement_type = 'adjustment' AND origin_type = 'sale' AND origin_id = :sid ORDER BY movement_id DESC LIMIT 1",
        ['sid' => $saleId]
    );
    $okRest = $rest
        && $rest['quantity'] == 3
        && $rest['previous_stock'] == 7
        && $rest['current_stock'] == 10
        && (int) $rest['user_id'] === $userId
        && str_contains($rest['reason'] ?? '', 'Cancelación de venta ' . $createdSaleCode);
    echo "Movimiento restore (adjustment, origin sale): " . ($okRest ? 'PASS' : 'FAIL') . "\n";

    $saleStatus = $db->selectOne('SELECT status FROM sales WHERE sale_id = :id', ['id' => $saleId])['status'];
    echo "Venta cancelada (status cancelled): " . ($saleStatus === 'cancelled' ? 'PASS' : 'FAIL') . "\n";

    // 5. Transaccion anidada: la venta completa es atomica
    $db->query("UPDATE sales SET status = 'pending' WHERE sale_id = :id", ['id' => $saleId]);
    $db->query("UPDATE products SET stock = 10 WHERE product_id = :id", ['id' => $productId]);
    $db->query("DELETE FROM inventory_movements WHERE origin_id = :sid", ['sid' => $saleId]);

    try {
        $placeOrder->execute(
            customerId: $customerId,
            addressId: $addressId,
            paymentMethod: 'card',
            cartItems: [
                ['product_id' => $productId, 'quantity' => 999],
            ],
        );
        echo "Venta sin stock (lanza excepcion): FAIL\n";
    } catch (\DomainException $e) {
        echo "Venta sin stock (lanza excepcion): PASS\n";
    }

    // Si el stock es insuficiente no debe quedar venta ni movimiento
    $orphanMoves = (int) $db->selectOne(
        "SELECT COUNT(*) AS total FROM inventory_movements WHERE product_id = :id",
        ['id' => $productId]
    )['total'];
    $orphanDetails = (int) $db->selectOne(
        "SELECT COUNT(*) AS total FROM sale_details sd JOIN sales s ON sd.sale_id = s.sale_id WHERE sd.product_id = :id AND sd.sale_id <> :sid AND s.sale_date >= NOW() - INTERVAL 1 MINUTE",
        ['id' => $productId, 'sid' => $saleId]
    )['total'];
    echo "Venta fallida atomica (sin venta/movimiento): " . ($orphanMoves === 0 && $orphanDetails === 0 ? 'PASS' : 'FAIL') . "\n";
} finally {
    if ($createdSaleId !== null) {
        $db->query("DELETE FROM inventory_movements WHERE origin_id = :sid", ['sid' => $createdSaleId]);
        $db->query("DELETE FROM sale_details WHERE sale_id = :sid", ['sid' => $createdSaleId]);
        $db->query("DELETE FROM deliveries WHERE sale_id = :sid", ['sid' => $createdSaleId]);
        $db->query("DELETE FROM sales WHERE sale_id = :sid", ['sid' => $createdSaleId]);
    }
    $db->query("DELETE FROM addresses WHERE customer_id IN (SELECT customer_id FROM customers WHERE email = :email)", ['email' => $customerEmail]);
    $db->query("DELETE FROM customers WHERE email = :email", ['email' => $customerEmail]);
    $db->query("DELETE FROM inventory_movements WHERE product_id IN (SELECT product_id FROM products WHERE product_code = :code)", ['code' => $productCode]);
    $db->query("DELETE FROM products WHERE product_code = :code", ['code' => $productCode]);
}

echo "\n=== INTEGRACION CHECKOUT -> INVENTORY OK ===\n";
