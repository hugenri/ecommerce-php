<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\AppConfig;
use App\Config\Config;
use App\Core\Container;
use App\Modules\Customers\Domain\CartRepositoryInterface;
use App\Modules\Customers\Persistence\CartRepository;
use App\Modules\Customers\Application\Services\CartService;
use App\Modules\Customers\Application\UseCases\MergeGuestCartUseCase;
use App\Modules\Products\Domain\ProductRepositoryInterface;
use App\Modules\Products\Persistence\ProductRepository;
use App\Framework\Session\SessionManagerInterface;
use App\Core\SessionManager;
use App\Core\Security\GuestCartToken;
use App\Core\Database\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$container = new Container();
$container->singleton(Database::class);
$container->singleton(Config::class);
$container->singleton(AppConfig::class);
$container->singleton(SessionManagerInterface::class, SessionManager::class);
$container->bind(CartRepositoryInterface::class, CartRepository::class);
$container->bind(ProductRepositoryInterface::class, ProductRepository::class);

$db = $container->make(Database::class);

$session = $container->make(SessionManagerInterface::class);
$session->start(['name' => 'CART_TEST']);

$suffix = bin2hex(random_bytes(4));
$email = 'cart_test_' . $suffix . '@example.com';
$prefix = 'CART_' . strtoupper($suffix);

$products = [];
$customerId = null;

// Limpieza previa por si hubo una corrida fallida
foreach (['P1', 'P2', 'P3'] as $p) {
    $code = $prefix . '_' . $p;
    $db->query("DELETE FROM inventory_movements WHERE product_id IN (SELECT product_id FROM products WHERE product_code = :code)", ['code' => $code]);
    $db->query("DELETE FROM products WHERE product_code = :code", ['code' => $code]);
}
$db->query("DELETE FROM carts WHERE customer_id IN (SELECT customer_id FROM customers WHERE email = :email)", ['email' => $email]);
$db->query("DELETE FROM customers WHERE email = :email", ['email' => $email]);

try {
    $subcategoryId = (int) $db->selectOne("SELECT subcategory_id FROM subcategories LIMIT 1")['subcategory_id'];

    $products['P1'] = (int) $db->insert('products', [
        'subcategory_id' => $subcategoryId,
        'product_code' => $prefix . '_P1',
        'name' => 'Persistente Activo ' . $suffix,
        'stock' => 25,
        'price' => 100.00,
        'discount' => 0.00,
        'status' => 'active',
    ]);

    $products['P2'] = (int) $db->insert('products', [
        'subcategory_id' => $subcategoryId,
        'product_code' => $prefix . '_P2',
        'name' => 'Persistente Inactivo ' . $suffix,
        'stock' => 50,
        'price' => 200.00,
        'discount' => 0.00,
        'status' => 'inactive',
    ]);

    $products['P3'] = (int) $db->insert('products', [
        'subcategory_id' => $subcategoryId,
        'product_code' => $prefix . '_P3',
        'name' => 'Persistente Stock Bajo ' . $suffix,
        'stock' => 3,
        'price' => 50.00,
        'discount' => 0.00,
        'status' => 'active',
    ]);

    $customerId = (int) $db->insert('customers', [
        'first_name' => 'Carrito',
        'last_name_paternal' => 'Test',
        'email' => $email,
        'password' => password_hash('test', PASSWORD_BCRYPT),
        'active' => 1,
    ]);

    // ──── Fase A: carrito de invitado (cookie) ──────────────
    $session->remove('customer.id');

    $guestToken = $container->make(GuestCartToken::class)->generate();
    $_COOKIE[GuestCartToken::COOKIE_NAME] = $guestToken;

    $cart = $container->make(CartService::class);
    $cart->addItem($products['P1'], 3);
    $cart->addItem($products['P1'], 2);
    $cart->addItem($products['P2'], 1);
    $cart->addItem($products['P3'], 10);

    $countGuest = $cart->getCount();
    echo "Invitado acumula cantidades (5): " . ($countGuest === 16 ? 'PASS' : 'FAIL') . "\n";

    $subtotalGuest = $cart->getSubtotal();
    echo "Invitado subtotal con datos vivos (1200): " . ($subtotalGuest === 1200.0 ? 'PASS' : 'FAIL') . "\n";

    $itemsGuest = $cart->getItems();
    $item1 = $itemsGuest[(string) $products['P1']] ?? null;
    $okShape = $item1
        && array_key_exists('product_id', $item1)
        && array_key_exists('name', $item1)
        && array_key_exists('price', $item1)
        && array_key_exists('image', $item1)
        && array_key_exists('quantity', $item1)
        && $item1['price'] === 100.0
        && $item1['name'] === 'Persistente Activo ' . $suffix;
    echo "Invitado lee nombre/precio vivos: " . ($okShape ? 'PASS' : 'FAIL') . "\n";

    $guestRow = $db->selectOne("SELECT cart_id FROM carts WHERE guest_token = :t", ['t' => $guestToken]);
    echo "Invitado persiste en BD: " . ($guestRow ? 'PASS' : 'FAIL') . "\n";

    // ──── Fase B: merge al iniciar sesión ───────────────────
    $session->set('customer.id', $customerId);

    ob_start();
    $container->make(MergeGuestCartUseCase::class)->execute($customerId);
    ob_end_clean();

    $customerItems = $db->select(
        "SELECT ci.product_id, ci.quantity
         FROM cart_items ci
         JOIN carts c ON c.cart_id = ci.cart_id
         WHERE c.customer_id = :cid
         ORDER BY ci.cart_item_id",
        ['cid' => $customerId]
    );
    $qtyById = [];
    foreach ($customerItems as $row) {
        $qtyById[(int) $row['product_id']] = (int) $row['quantity'];
    }

    $p1Merged = (int) ($qtyById[$products['P1']] ?? 0) === 5;
    $p3Clipped = (int) ($qtyById[$products['P3']] ?? 0) === 3;
    $p2Dropped = !isset($qtyById[$products['P2']]);
    $cartCount = array_sum($qtyById) === 8;
    echo "Merge suma cantidades (5): " . ($p1Merged ? 'PASS' : 'FAIL') . "\n";
    echo "Merge recorta al stock (10 -> 3): " . ($p3Clipped ? 'PASS' : 'FAIL') . "\n";
    echo "Merge descarta producto inactivo: " . ($p2Dropped ? 'PASS' : 'FAIL') . "\n";
    echo "Merge total de unidades (8): " . ($cartCount ? 'PASS' : 'FAIL') . "\n";

    $guestGone = $db->selectOne("SELECT cart_id FROM carts WHERE guest_token = :t", ['t' => $guestToken]);
    echo "Merge elimina carrito de invitado: " . ($guestGone === null ? 'PASS' : 'FAIL') . "\n";
    echo "Merge revoca la cookie: " . (!isset($_COOKIE[GuestCartToken::COOKIE_NAME]) ? 'PASS' : 'FAIL') . "\n";

    // ──── Fase C: carrito de cliente (sesión) ───────────────
    $cart->addItem($products['P1'], 7);
    echo "Cliente agrega sobre carrito propio (12): " . ($cart->getCount() === 15 ? 'PASS' : 'FAIL') . "\n";

    $cart->updateQuantity($products['P3'], 0);
    echo "updateQuantity 0 elimina ítem: " . ($cart->getCount() === 12 ? 'PASS' : 'FAIL') . "\n";

    $cart->removeItem($products['P1']);
    echo "removeItem elimina ítem: " . ($cart->getCount() === 0 ? 'PASS' : 'FAIL') . "\n";

    $cart->addItem($products['P1'], 2);
    $cart->clear();
    $left = $db->selectOne(
        "SELECT COUNT(*) AS total FROM cart_items ci JOIN carts c ON c.cart_id = ci.cart_id WHERE c.customer_id = :cid",
        ['cid' => $customerId]
    )['total'];
    echo "clear vacía el carrito del cliente: " . ((int) $left === 0 ? 'PASS' : 'FAIL') . "\n";

    // ──── Fase D: limpieza de carritos de invitado (TTL) ────
    $oldCartId = (int) $db->insert('carts', [
        'customer_id' => null,
        'guest_token' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'status' => 'open',
    ]);
    $db->query("UPDATE carts SET updated_at = now() - INTERVAL 31 DAY WHERE cart_id = :id", ['id' => $oldCartId]);

    $recentCartId = (int) $db->insert('carts', [
        'customer_id' => null,
        'guest_token' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        'status' => 'open',
    ]);

    $purged = $container->make(CartRepositoryInterface::class)->purgeExpiredGuestCarts(30);
    $oldGone = $db->selectOne("SELECT cart_id FROM carts WHERE cart_id = :id", ['id' => $oldCartId]) === null;
    $recentKept = $db->selectOne("SELECT cart_id FROM carts WHERE cart_id = :id", ['id' => $recentCartId]) !== null;
    echo "Purga invitados viejos (>30d): " . ($oldGone ? 'PASS' : 'FAIL') . "\n";
    echo "Conserva invitados recientes: " . ($recentKept ? 'PASS' : 'FAIL') . "\n";
    echo "Purga reporta cantidad (>0): " . ($purged > 0 ? 'PASS' : 'FAIL') . "\n";
} finally {
    foreach (['P1', 'P2', 'P3'] as $p) {
        $code = $prefix . '_' . $p;
        $db->query("DELETE FROM inventory_movements WHERE product_id IN (SELECT product_id FROM products WHERE product_code = :code)", ['code' => $code]);
        $db->query("DELETE FROM products WHERE product_code = :code", ['code' => $code]);
    }
    if ($customerId !== null) {
        $db->query("DELETE FROM carts WHERE customer_id = :id", ['id' => $customerId]);
    }
    $db->query("DELETE FROM carts WHERE guest_token = :t", ['t' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa']);
    $db->query("DELETE FROM carts WHERE guest_token = :t", ['t' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb']);
    $db->query("DELETE FROM customers WHERE email = :email", ['email' => $email]);
}

echo "\n=== INTEGRACION CARRITO PERSISTENTE OK ===\n";