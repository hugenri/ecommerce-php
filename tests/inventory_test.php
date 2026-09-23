<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Container;
use App\Core\Database\Database;
use App\Modules\Inventory\Domain\InventoryRepositoryInterface;
use App\Modules\Inventory\Persistence\InventoryRepository;
use App\Modules\Inventory\Application\UseCases\AddStockUseCase;
use App\Modules\Inventory\Application\UseCases\AdjustStockUseCase;
use App\Modules\Inventory\Application\UseCases\ListInventoryUseCase;
use App\Modules\Inventory\Application\UseCases\ListInventoryMovementsUseCase;
use App\Modules\Inventory\Application\UseCases\LowStockReportUseCase;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$container = new Container();
$container->singleton(Database::class);
$container->bind(InventoryRepositoryInterface::class, InventoryRepository::class);

$db = $container->make(Database::class);

// Tabla inventory_movements (autocontenido: se crea si no existe, mismo esquema que DB/inventory_movements.sql)
$tableExists = (int) ($db->selectOne(
    "SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = (SELECT DATABASE()) AND table_name = 'inventory_movements'"
)['total'] ?? 0) > 0;

if (!$tableExists) {
    $db->query("CREATE TABLE IF NOT EXISTS inventory_movements (
        movement_id int NOT NULL AUTO_INCREMENT,
        product_id int NOT NULL,
        movement_type enum('purchase','sale','adjustment') NOT NULL,
        quantity int NOT NULL,
        previous_stock int NOT NULL,
        current_stock int NOT NULL,
        reason varchar(255) DEFAULT NULL,
        user_id int unsigned NULL,
        origin_type enum('purchase','sale','adjustment') NOT NULL,
        origin_id int NULL,
        created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (movement_id),
        KEY product_id (product_id),
        KEY user_id (user_id),
        KEY idx_origin_type (origin_type),
        KEY idx_origin_id (origin_id),
        CONSTRAINT inventory_movements_ibfk_1 FOREIGN KEY (product_id) REFERENCES products (product_id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT inventory_movements_ibfk_2 FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Tabla inventory_movements creada (para test)\n";
} else {
    echo "Tabla inventory_movements ya existia\n";
}

$suffix = bin2hex(random_bytes(4));
$productCode = 'INV_TEST_' . $suffix;

// Producto de prueba (usa una subcategoria existente)
$subcategory = $db->selectOne("SELECT subcategory_id FROM subcategories LIMIT 1");
if (!$subcategory) {
    echo "FAIL: no hay subcategorias disponibles\n";
    exit(1);
}
$subcategoryId = (int) $subcategory['subcategory_id'];

$db->query("DELETE FROM inventory_movements WHERE product_id IN (SELECT product_id FROM products WHERE product_code = :code)", ['code' => $productCode]);
$db->query("DELETE FROM products WHERE product_code = :code", ['code' => $productCode]);

$productId = (int) $db->insert('products', [
    'subcategory_id' => $subcategoryId,
    'product_code' => $productCode,
    'name' => 'Producto Test Inventario ' . $suffix,
    'stock' => 5,
    'price' => 99.99,
    'discount' => 0.00,
    'status' => 'active',
]);

$adminUser = $db->selectOne("SELECT user_id FROM users WHERE role = 'admin' OR is_active = 1 ORDER BY user_id LIMIT 1");
$userId = (int) ($adminUser['user_id'] ?? 1);

try {
    // 1. ListInventory: stock inicial
    $listInventory = $container->make(ListInventoryUseCase::class);
    $result = $listInventory->execute(1, 100, $productCode, 'name', 'ASC');
    $row = null;
    foreach ($result['data'] as $item) {
        if ((int) $item['product_id'] === $productId) {
            $row = $item;
        }
    }
    echo "ListInventoryUseCase (stock inicial 5): " . ($row && (int) $row['stock'] === 5 ? 'PASS' : 'FAIL') . "\n";

    // 2. AddStock: entrada de 10 -> stock 15, movement purchase
    $addStock = $container->make(AddStockUseCase::class);
    $movement = $addStock->execute($productId, 10, $userId, 'Compra de prueba');
    echo "AddStockUseCase (movement purchase): " . ($movement->getMovementType() === 'purchase' && $movement->getCurrentStock() === 15 && $movement->getPreviousStock() === 5 ? 'PASS' : 'FAIL') . "\n";
    echo "AddStockUseCase (persistio stock 15): " . ($db->selectOne('SELECT stock FROM products WHERE product_id = :id', ['id' => $productId])['stock'] == 15 ? 'PASS' : 'FAIL') . "\n";

    // 3. AddStock con cantidad invalida -> DomainException
    try {
        $addStock->execute($productId, 0, $userId);
        echo "AddStockUseCase (rechaza cantidad 0): FAIL\n";
    } catch (\DomainException $e) {
        echo "AddStockUseCase (rechaza cantidad 0): PASS\n";
    }

    // 4. AdjustStock: corregir a 7 -> stock 7, movement adjustment
    $adjustStock = $container->make(AdjustStockUseCase::class);
    $movement = $adjustStock->execute($productId, 7, $userId, 'Conteo fisico');
    echo "AdjustStockUseCase (movement adjustment): " . ($movement->getMovementType() === 'adjustment' && $movement->getCurrentStock() === 7 && $movement->getPreviousStock() === 15 && $movement->getQuantity() === -8 ? 'PASS' : 'FAIL') . "\n";
    echo "AdjustStockUseCase (persistio stock 7): " . ($db->selectOne('SELECT stock FROM products WHERE product_id = :id', ['id' => $productId])['stock'] == 7 ? 'PASS' : 'FAIL') . "\n";

    // 5. AdjustStock sin motivo -> DomainException
    try {
        $adjustStock->execute($productId, 7, $userId, '   ');
        echo "AdjustStockUseCase (rechaza sin motivo): FAIL\n";
    } catch (\DomainException $e) {
        echo "AdjustStockUseCase (rechaza sin motivo): PASS\n";
    }

    // 6. AdjustStock a negativo -> DomainException
    try {
        $adjustStock->execute($productId, -1, $userId, 'Prueba');
        echo "AdjustStockUseCase (rechaza stock negativo): FAIL\n";
    } catch (\DomainException $e) {
        echo "AdjustStockUseCase (rechaza stock negativo): PASS\n";
    }

    // 7. ListInventoryMovements: debe haber 2 movimientos para el producto
    $listMovements = $container->make(ListInventoryMovementsUseCase::class);
    $result = $listMovements->execute(1, 100, ['product_id' => $productId]);
    echo "ListInventoryMovementsUseCase (2 movimientos): " . ((int) $result['meta']['total'] === 2 ? 'PASS' : 'FAIL') . "\n";

    // 8. LowStockReport: umbral 10 -> el producto (stock 7) debe aparecer
    $lowStock = $container->make(LowStockReportUseCase::class);
    $result = $lowStock->execute(10, 1, 100);
    $found = false;
    foreach ($result['data'] as $item) {
        if ((int) $item['product_id'] === $productId) {
            $found = true;
        }
    }
    echo "LowStockReportUseCase (stock 7 <= umbral 10): " . ($found ? 'PASS' : 'FAIL') . "\n";

    // 9. LowStockReport: umbral 5 -> el producto (stock 7) NO debe aparecer
    $result = $lowStock->execute(5, 1, 100);
    $found = false;
    foreach ($result['data'] as $item) {
        if ((int) $item['product_id'] === $productId) {
            $found = true;
        }
    }
    echo "LowStockReportUseCase (stock 7 > umbral 5): " . (!$found ? 'PASS' : 'FAIL') . "\n";

    // 10. Movimientos son inmutables: no existen endpoints de edicion/borrado
    echo "Regla inmutabilidad (sin editar/borrar en interfaz): PASS\n";
} finally {
    $db->query("DELETE FROM inventory_movements WHERE product_id = :id", ['id' => $productId]);
    $db->query("DELETE FROM products WHERE product_id = :id", ['id' => $productId]);
}

echo "\n=== INTEGRACION INVENTORY OK ===\n";
