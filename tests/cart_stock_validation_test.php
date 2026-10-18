<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\AppConfig;
use App\Config\Config;
use App\Core\Container;
use App\Core\Database\Database;
use App\Core\Security\GuestCartToken;
use App\Core\SessionManager;
use App\Framework\Session\SessionManagerInterface;
use App\Modules\Customers\Application\Services\CartService;
use App\Modules\Customers\Application\UseCases\AddToCartUseCase;
use App\Modules\Customers\Application\UseCases\UpdateCartQuantityUseCase;
use App\Modules\Customers\Domain\CartRepositoryInterface;
use App\Modules\Customers\Persistence\CartRepository;
use App\Modules\Products\Domain\ProductRepositoryInterface;
use App\Modules\Products\Persistence\ProductRepository;

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
$session->start(['name' => 'STOCK_TEST']);

$suffix = bin2hex(random_bytes(4));
$email = 'stock_test_' . $suffix . '@example.com';
$prefix = 'STK_' . strtoupper($suffix);

$failures = 0;
function check(string $label, bool $ok): void
{
    global $failures;
    if (!$ok) {
        $failures++;
    }
    echo $label . ': ' . ($ok ? 'PASS' : 'FAIL') . "\n";
}

$productIds = [];
$customerId = null;

foreach (['P5', 'P3'] as $p) {
    $code = $prefix . '_' . $p;
    $db->query("DELETE FROM inventory_movements WHERE product_id IN (SELECT product_id FROM products WHERE product_code = :code)", ['code' => $code]);
    $db->query("DELETE FROM products WHERE product_code = :code", ['code' => $code]);
}
$db->query("DELETE FROM carts WHERE customer_id IN (SELECT customer_id FROM customers WHERE email = :email)", ['email' => $email]);
$db->query("DELETE FROM customers WHERE email = :email", ['email' => $email]);

try {
    $subcategoryRow = $db->selectOne("SELECT subcategory_id FROM subcategories LIMIT 1");
    if (!$subcategoryRow) {
        echo "FAIL: no hay subcategorias disponibles\n";
        exit(1);
    }
    $subcategoryId = (int) $subcategoryRow['subcategory_id'];

    $createProduct = function (string $key, int $stock) use ($db, $subcategoryId, $prefix, $suffix): int {
        $productId = (int) $db->insert('products', [
            'subcategory_id' => $subcategoryId,
            'product_code' => $prefix . '_' . $key,
            'name' => 'Stock ' . $key . ' ' . $suffix,
            'stock' => $stock,
            'price' => 10.00,
            'discount' => 0.00,
            'status' => 'active',
        ]);
        $db->insert('inventory_movements', [
            'product_id' => $productId,
            'movement_type' => 'purchase',
            'quantity' => $stock,
            'previous_stock' => 0,
            'current_stock' => $stock,
            'reason' => 'test validacion stock',
            'user_id' => null,
            'origin_type' => 'purchase',
            'origin_id' => null,
        ]);
        return $productId;
    };

    $productIds['P5'] = $createProduct('P5', 5);
    $productIds['P3'] = $createProduct('P3', 3);

    $customerId = (int) $db->insert('customers', [
        'first_name' => 'Stock',
        'last_name_paternal' => 'Test',
        'email' => $email,
        'password' => password_hash('test', PASSWORD_BCRYPT),
        'active' => 1,
    ]);

    $add = $container->make(AddToCartUseCase::class);
    $update = $container->make(UpdateCartQuantityUseCase::class);
    $cart = $container->make(CartService::class);

    // ═══ Fase A: invitado (cookie) ═════════════════════════
    $session->remove('customer.id');
    $guestToken = $container->make(GuestCartToken::class)->generate();
    $_COOKIE[GuestCartToken::COOKIE_NAME] = $guestToken;

    $p5 = $productIds['P5'];

    $add->execute($p5, 1);
    check('Invitado: agregar 1 (stock 5) -> carrito 1', $cart->getItemQuantity($p5) === 1);

    $add->execute($p5, 4);
    check('Invitado: agregar 4 mas -> carrito 5 (maximo)', $cart->getItemQuantity($p5) === 5);

    $rejected = false;
    try {
        $add->execute($p5, 1);
    } catch (\DomainException $e) {
        $rejected = true;
    }
    check('Invitado: agregar 1 cuando ya hay 5 -> RECHAZADO', $rejected);
    check('Invitado: carrito sigue en 5 tras rechazo', $cart->getItemQuantity($p5) === 5);

    $manipulated = false;
    try {
        $add->execute((string) $p5, '6');
    } catch (\DomainException $e) {
        $manipulated = true;
    }
    check('Invitado: solicitud manipulada (6 sobre stock 5) -> RECHAZADO', $manipulated);
    check('Invitado: carrito sin cambios tras manipulacion', $cart->getItemQuantity($p5) === 5);

    // Stock no se descuenta al agregar al carrito
    $dbStock = (int) $db->selectOne("SELECT stock FROM products WHERE product_id = :id", ['id' => $p5])['stock'];
    check('Agregar al carrito NO descuenta stock del producto', $dbStock === 5);

    // updateQuantity a un valor valido
    $update->execute($p5, 2);
    check('Invitado: update a 2 (<= stock) -> aceptado', $cart->getItemQuantity($p5) === 2);

    // updateQuantity 5 -> 6 (el caso exigido)
    $rejectedUpdate = false;
    try {
        $update->execute($p5, 6);
    } catch (\DomainException $e) {
        $rejectedUpdate = true;
    }
    check('Invitado: update 5->6 sobre stock 5 -> RECHAZADO', $rejectedUpdate);
    check('Invitado: carrito se mantiene valido (2) tras rechazo', $cart->getItemQuantity($p5) === 2);

    // manipulacion directa del cuerpo HTTP (string fuera de rango)
    $manipulatedUpdate = false;
    try {
        $update->execute((string) $p5, '999');
    } catch (\DomainException $e) {
        $manipulatedUpdate = true;
    }
    check('Invitado: update manipulado (999) -> RECHAZADO', $manipulatedUpdate);

    // update a 0 elimina
    $update->execute($p5, 0);
    check('Invitado: update a 0 elimina el item', $cart->getItemQuantity($p5) === 0);

    // Cantidad que excede lo disponible parcialmente (stock 3, pide 4)
    $p3 = $productIds['P3'];
    $partial = false;
    try {
        $add->execute($p3, 4);
    } catch (\DomainException $e) {
        $partial = true;
    }
    check('Invitado: agregar 4 a stock 3 -> RECHAZADO (no agrega parcial)', $partial);
    check('Invitado: el item de stock 3 quedo vacio', $cart->getItemQuantity($p3) === 0);

    // ═══ Fase B: limpiar invitado ══════════════════════════
    $cart->clear();

    // ═══ Fase C: usuario autenticado ═══════════════════════
    $session->set('customer.id', $customerId);

    $add->execute($p5, 3);
    check('Autenticado: agregar 3 -> carrito 3', $cart->getItemQuantity($p5) === 3);

    $authRejected = false;
    try {
        $add->execute($p5, 3);
    } catch (\DomainException $e) {
        $authRejected = true;
    }
    check('Autenticado: agregar 3 mas (total 6 > stock 5) -> RECHAZADO', $authRejected);
    check('Autenticado: carrito sigue en 3', $cart->getItemQuantity($p5) === 3);

    $authUpdateAccepted = true;
    try {
        $update->execute($p5, 5);
    } catch (\DomainException $e) {
        $authUpdateAccepted = false;
    }
    check('Autenticado: update a 5 (== stock) -> aceptado', $authUpdateAccepted && $cart->getItemQuantity($p5) === 5);

    $over = false;
    try {
        $update->execute($p5, 6);
    } catch (\DomainException $e) {
        $over = true;
    }
    check('Autenticado: update 5->6 -> RECHAZADO', $over);
    check('Autenticado: carrito permanece en 5', $cart->getItemQuantity($p5) === 5);

    // ═══ Fase D: producto invalido / inactivo ══════════════
    $invalid = false;
    try {
        $add->execute(0, 1);
    } catch (\DomainException $e) {
        $invalid = true;
    }
    check('Producto invalido (0) -> RECHAZADO', $invalid);

    $invalidUpdate = false;
    try {
        $update->execute(999999999, 1);
    } catch (\DomainException $e) {
        $invalidUpdate = true;
    }
    check('Update sobre producto inexistente -> RECHAZADO', $invalidUpdate);
} finally {
    foreach (['P5', 'P3'] as $p) {
        $code = $prefix . '_' . $p;
        $db->query("DELETE FROM inventory_movements WHERE product_id IN (SELECT product_id FROM products WHERE product_code = :code)", ['code' => $code]);
        $db->query("DELETE FROM products WHERE product_code = :code", ['code' => $code]);
    }
    if ($customerId !== null) {
        $db->query("DELETE FROM carts WHERE customer_id = :id", ['id' => $customerId]);
    }
    if (isset($guestToken)) {
        $db->query("DELETE FROM carts WHERE guest_token = :t", ['t' => $guestToken]);
    }
    $db->query("DELETE FROM customers WHERE email = :email", ['email' => $email]);
}

echo "\n" . ($failures === 0 ? '=== VALIDACION DE STOCK OK ===' : "=== VALIDACION DE STOCK CON {$failures} FALLOS ===") . "\n";
exit($failures === 0 ? 0 : 1);
