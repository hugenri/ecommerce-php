<?php

declare(strict_types=1);

$cartFile = __DIR__ . '/../public/js/cart.js';
$miniFile = __DIR__ . '/../public/js/mini-cart.js';

if (!is_file($cartFile) || !is_file($miniFile)) {
    echo "FAIL - no se encontro public/js/cart.js o public/js/mini-cart.js\n";
    exit(1);
}

$cart = file_get_contents($cartFile);
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

// ── mini-cart.js: publica el payload autoritativo ──
check(
    'mini-cart publica el evento cart:updated con el payload',
    str_contains($mini, "document.dispatchEvent(new CustomEvent('cart:updated', { detail: payload }));")
);
check(
    'mini-cart emite el evento desde render() (todos los flujos)',
    (bool) preg_match('/function render\(payload\)[\s\S]*?document\.dispatchEvent\(new CustomEvent\(\'cart:updated\'/', $mini)
);
check(
    'mini-cart emite tras syncPageStock (payload completo ya disponible)',
    (bool) preg_match('/syncPageStock\(payload\);\s*document\.dispatchEvent\(new CustomEvent\(\'cart:updated\'/', $mini)
);
check(
    'mini-cart reutiliza render(cartCache) al eliminar y al cambiar cantidad',
    substr_count($mini, 'render(cartCache);') >= 3
);

// ── cart.js: escucha y reutiliza render() existente ──
check(
    'cart.js escucha cart:updated',
    str_contains($cart, "document.addEventListener('cart:updated'")
);
check(
    'cart.js reutiliza render(e.detail) sin lógica nueva',
    (bool) preg_match("/addEventListener\('cart:updated'[\s\S]*?render\(e\.detail\)/", $cart)
);
check(
    'cart.js define una sola función render(payload) (sin duplicar)',
    substr_count($cart, 'function render(payload)') === 1
);
check(
    'cart.js render sincroniza filas/cantidades/subtotal de línea',
    str_contains($cart, '[data-qty-input]') && str_contains($cart, '[data-cart-subtotal]')
);
check(
    'cart.js render sincroniza substotal, IVA y total generales',
    str_contains($cart, '[data-cart-summary-subtotal]')
        && str_contains($cart, '[data-cart-summary-iva]')
        && str_contains($cart, '[data-cart-summary-total]')
);
check(
    'cart.js render sincroniza el badge (CartCounter)',
    str_contains($cart, 'window.CartCounter.set(payload.count || 0)')
);
check(
    'cart.js render retira filas eliminadas',
    str_contains($cart, 'row.parentNode.removeChild(row)')
);

// ── No se introducen regresiones del hallazgo de Fase 1/2 ──
check(
    'mini-cart conserva el aviso "Stock máximo alcanzado"',
    str_contains($mini, "'Stock máximo alcanzado'")
);

$expected = 12;
echo "\n=== SYNC MINI CART / CART PAGE: " . ($expected - $failures) . "/{$expected} OK ===\n";
exit($failures === 0 ? 0 : 2);
