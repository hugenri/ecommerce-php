<?php

declare(strict_types=1);

$file = __DIR__ . '/../public/js/mini-cart.js';
if (!is_file($file)) {
    echo "FAIL - no se encontro public/js/mini-cart.js\n";
    exit(1);
}

$js = file_get_contents($file);

$failures = 0;
function check(string $label, bool $ok): void
{
    global $failures;
    if (!$ok) {
        $failures++;
    }
    echo $label . ': ' . ($ok ? 'PASS' : 'FAIL') . "\n";
}

function syncItemStateBody(string $js): string
{
    $start = strpos($js, 'function syncItemState(');
    if ($start === false) {
        return '';
    }
    $end = strpos($js, 'function renderItem(', $start);
    return $end === false ? substr($js, $start) : substr($js, $start, $end - $start);
}

function buildItemBody(string $js): string
{
    $start = strpos($js, 'function buildItem(');
    if ($start === false) {
        return '';
    }
    $end = strpos($js, 'function syncItemState(', $start);
    return $end === false ? substr($js, $start) : substr($js, $start, $end - $start);
}

$sync = syncItemStateBody($js);
$build = buildItemBody($js);

check('syncItemState existe', $sync !== '');

check('Distingue stock numerico (hasStock)', str_contains($sync, "var hasStock = typeof item.stock === 'number';"));

check('Detecta agotado con stock <= 0', str_contains($sync, 'var outOfStock = hasStock && item.stock <= 0;'));

check('Detecta maximo alcanzado cuando quantity >= stock (stock > 0)', str_contains($sync, 'var atMax = hasStock && item.stock > 0 && quantity >= item.stock;'));

check('Lee la cantidad con parseInt(item.quantity)', str_contains($sync, 'var quantity = parseInt(item.quantity, 10) || 0;'));

check('Badge "Agotado" solo en rama outOfStock', (bool) preg_match("/if \(outOfStock\) \{\s*badge\.textContent = 'Agotado';/s", $sync));

check('Badge "Stock máximo alcanzado" solo en rama atMax', (bool) preg_match("/else if \(atMax\) \{\s*badge\.textContent = 'Stock máximo alcanzado';/s", $sync));

check('Badge visible (remove d-none) en outOfStock y atMax', substr_count($sync, "badge.classList.remove('d-none')") === 2);

check('Badge oculto (add d-none) cuando hay stock disponible', (bool) preg_match("/\} else \{\s*badge\.classList\.add\('d-none'\);\s*\}/s", $sync));

check('Ya no usa el toggle simple ligado solo a outOfStock', !str_contains($sync, "badge.classList.toggle('d-none', !outOfStock)"));

check('data-max sigue siendo el stock (no disponible)', str_contains($sync, "stepper.setAttribute('data-max', String(item.stock > 0 ? item.stock : 0));"));

check('Se sigue llamando QuantityStepper.sync (mantiene + deshabilitado al maximo)', str_contains($sync, 'global.QuantityStepper.sync(stepper || row);'));

check('El badge se crea oculto por defecto', str_contains($build, "'badge bg-warning text-dark mini-cart-stock-badge d-none'") || str_contains($build, "mini-cart-stock-badge d-none"));

check('El badge es accesible (role="status")', str_contains($build, "stockBadge.setAttribute('role', 'status');"));

$expected = 14;
echo "\n=== MINI CART STOCK BADGE: " . ($expected - $failures) . "/{$expected} OK ===\n";
exit($failures === 0 ? 0 : 2);
