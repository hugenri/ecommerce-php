<?php

declare(strict_types=1);

$viewFile = __DIR__ . '/../app/Modules/Store/Presentation/Views/product.php';
$miniFile = __DIR__ . '/../public/js/mini-cart.js';

if (!is_file($viewFile) || !is_file($miniFile)) {
    echo "FAIL - no se encontro product.php o mini-cart.js\n";
    exit(1);
}

$view = file_get_contents($viewFile);
$mini = file_get_contents($miniFile);

$failures = 0;
function check(string $label, bool $ok): void
{
    global $failures;
    if (!$ok) {
        $failures++;
    }
    echo $label . ': ' . ($ok ? 'PASS' : 'FAIL') . "\n";
}

function applyDetailStateBody(string $js): string
{
    $start = strpos($js, 'function applyDetailState(');
    if ($start === false) {
        return '';
    }
    $end = strpos($js, 'function syncPageStock(', $start);
    return $end === false ? substr($js, $start) : substr($js, $start, $end - $start);
}

$apply = applyDetailStateBody($mini);

// ── product.php: la celda lleva identificador estable de producto ──
check(
    'product.php marca la celda con data-product-id',
    (bool) preg_match('/<td data-detail-in-cart data-product-id="<\?= \(int\) \$product->getProductId\(\) \?>">/', $view)
);
check(
    'product.php conserva la cantidad renderizada (cartQuantity)',
    str_contains($view, 'data-detail-in-cart data-product-id=<?= (int) $product->getProductId() ?><?= $cartQuantity ?>')
        || (bool) preg_match('/data-detail-in-cart data-product-id="[^"]*"><\?= \$cartQuantity \?>/', $view)
);

// ── mini-cart.js: resuelve la celda fuera del scope por product_id ──
check('applyDetailState existe', $apply !== '');
check(
    'applyDetailState lee el product_id del scope',
    str_contains($apply, "var productId = scope.getAttribute('data-product-id');")
);
check(
    'applyDetailState cae al documento buscando la celda por product_id',
    str_contains($apply, "document.querySelector('[data-detail-in-cart][data-product-id=\"' + productId + '\"]')")
);
check(
    'Se conserva la búsqueda dentro del scope como primera opción',
    str_contains($apply, "scope.querySelector('[data-detail-in-cart]')")
);
check(
    'La fila sigue ocultándose cuando no hay unidades en el carrito',
    str_contains($apply, "inCartRow.classList.toggle('d-none', qty <= 0);")
);
check(
    'syncPageStock sigue aplicando el estado del detalle',
    str_contains($mini, 'applyDetailState(scope, stock, qty);')
);

// ── No se rompen las Fases 2 y 3 ──
check(
    'Fase 2 intacta: aviso "Stock máximo alcanzado"',
    str_contains($mini, "'Stock máximo alcanzado'")
);
check(
    'Fase 3 intacta: mini-cart publica cart:updated',
    str_contains($mini, "document.dispatchEvent(new CustomEvent('cart:updated', { detail: payload }));")
);

$expected = 10;
echo "\n=== PRODUCT DETAIL CART QUANTITY: " . ($expected - $failures) . "/{$expected} OK ===\n";
exit($failures === 0 ? 0 : 2);
