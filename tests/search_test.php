<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Container;
use App\Core\Database\Database;
use App\Core\Http\Response;
use App\Core\Session\Store\SessionStore;
use App\Core\SessionManager;
use App\Framework\Session\SessionManagerInterface;
use App\Framework\Session\Store\SessionStoreInterface;
use App\Http\Request;
use App\Modules\Categories\Domain\CategoryRepositoryInterface;
use App\Modules\Categories\Persistence\CategoryRepository;
use App\Modules\Products\Domain\ProductRepositoryInterface;
use App\Modules\Products\Persistence\ProductRepository;
use App\Modules\Customers\Domain\CartRepositoryInterface;
use App\Modules\Customers\Persistence\CartRepository;
use App\Modules\Store\Presentation\Controllers\CatalogController;
use App\Modules\Subcategories\Domain\SubcategoryRepositoryInterface;
use App\Modules\Subcategories\Persistence\SubcategoryRepository;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$container = new Container();
$container->singleton(Database::class);
$container->bind(SessionStoreInterface::class, SessionStore::class);
$container->singleton(SessionManagerInterface::class, SessionManager::class);
$container->singleton(Response::class);
$container->bind(ProductRepositoryInterface::class, ProductRepository::class);
$container->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
$container->bind(SubcategoryRepositoryInterface::class, SubcategoryRepository::class);
$container->bind(CartRepositoryInterface::class, CartRepository::class);

$db = $container->make(Database::class);
$repo = $container->make(ProductRepositoryInterface::class);

ob_start();

$suffix = bin2hex(random_bytes(3));
$subcategory = $db->selectOne("SELECT subcategory_id FROM subcategories LIMIT 1");
if (!$subcategory) {
    echo "FAIL: no hay subcategorias disponibles\n";
    exit(1);
}
$subcategoryId = (int) $subcategory['subcategory_id'];

$createdCodes = [];

function insertSearchProduct(string $name, string $status = 'active', bool $withMovement = true): int
{
    global $db, $subcategoryId, $suffix, $createdCodes;
    $code = 'SRCH_' . bin2hex(random_bytes(3));
    $createdCodes[] = $code;
    $productId = (int) $db->insert('products', [
        'subcategory_id' => $subcategoryId,
        'product_code' => $code,
        'name' => $name,
        'stock' => 5,
        'price' => 9.99,
        'discount' => 0.00,
        'status' => $status,
    ]);
    if ($withMovement) {
        $db->insert('inventory_movements', [
            'product_id' => $productId,
            'movement_type' => 'purchase',
            'quantity' => 5,
            'previous_stock' => 0,
            'current_stock' => 5,
            'reason' => 'test busqueda',
            'user_id' => null,
            'origin_type' => 'purchase',
            'origin_id' => null,
        ]);
    }
    return $productId;
}

function productIds(array $products): array
{
    return array_map(fn($p) => (int) $p->getProductId(), $products);
}

function containsId(array $ids, int $id): bool
{
    return in_array($id, $ids, true);
}

function controllerWith(array $get): CatalogController
{
    global $container;
    $server = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/shop',
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_USER_AGENT' => 'php-test',
    ];
    $container->instance(Request::class, new Request($server, $get));
    return $container->make(CatalogController::class);
}

$created = [];

try {
    // ── Datos de prueba ──
    $created['visible1'] = insertSearchProduct('Martillo Bola ' . $suffix, 'active', true);
    $created['visible2'] = insertSearchProduct('Martillo Carpintero ' . $suffix, 'active', true);
    $created['inactive1'] = insertSearchProduct('Martillo Inactivo ' . $suffix, 'inactive', true);
    $created['noMovement1'] = insertSearchProduct('Martillo SinMov ' . $suffix, 'active', false);
    $created['prefix1'] = insertSearchProduct('Zeta Prefijo ' . $suffix, 'active', true);
    $created['contains1'] = insertSearchProduct('Omega Zeta Oeste ' . $suffix, 'active', true);
    $created['exactGoma'] = insertSearchProduct('Martillo de Goma ' . $suffix, 'active', true);
    $created['relatedGoma'] = insertSearchProduct('Martillo de Goma ' . $suffix . ' 8 oz', 'active', true);
    $created['quote'] = insertSearchProduct('Llave Inglesa 12" ' . $suffix, 'active', true);
    $created['ampersand'] = insertSearchProduct('Caja & Herramienta ' . $suffix, 'active', true);

    $limitIds = [];
    for ($i = 1; $i <= 10; $i++) {
        $limitIds[] = insertSearchProduct('LimiteBusq ' . $i . ' ' . $suffix, 'active', true);
    }

    // 1. Busqueda con coincidencias
    $found = productIds($repo->searchSuggestions('Martillo Bola ' . $suffix, 8));
    echo '1. Busqueda con coincidencias: ' . (containsId($found, $created['visible1']) ? 'PASS' : 'FAIL') . "\n";

    // 2. Busqueda sin coincidencias
    echo '2. Busqueda sin coincidencias: ' . ($repo->searchSuggestions('zzzznada_no_existe', 8) === [] ? 'PASS' : 'FAIL') . "\n";

    // 3. Busqueda parcial (subcadena del nombre)
    $found = productIds($repo->searchSuggestions('tillo Bola', 8));
    echo '3. Busqueda parcial: ' . (containsId($found, $created['visible1']) ? 'PASS' : 'FAIL') . "\n";

    // 4. Busqueda vacia (sin filtro el catálogo no queda vacío)
    $emptyOk = $repo->searchSuggestions('', 8) === []
        && $repo->paginateCatalog(page: 1, perPage: 12, search: '')['meta']['total'] > 0;
    echo '4. Busqueda vacia: ' . ($emptyOk ? 'PASS' : 'FAIL') . "\n";

    // 5. Busqueda con espacios
    $found = productIds($repo->searchSuggestions('   Martillo Bola ' . $suffix . '   ', 8));
    echo '5. Busqueda con espacios: ' . (containsId($found, $created['visible1']) ? 'PASS' : 'FAIL') . "\n";

    // 6. Case-insensitive (collation utf8mb4_unicode_ci)
    $found = productIds($repo->searchSuggestions(strtoupper('martillo bola ' . $suffix), 8));
    echo '6. Case-insensitive: ' . (containsId($found, $created['visible1']) ? 'PASS' : 'FAIL') . "\n";

    // 7. Producto inactivo / sin registro de stock no visible
    $found = productIds($repo->searchSuggestions('Martillo Inactivo ' . $suffix, 8));
    $notInactive = !containsId($found, $created['inactive1']);
    $found = productIds($repo->searchSuggestions('Martillo SinMov ' . $suffix, 8));
    $notNoMovement = !containsId($found, $created['noMovement1']);
    echo '7. Producto inactivo no visible: ' . ($notInactive && $notNoMovement ? 'PASS' : 'FAIL') . "\n";

    // 8. Limite de sugerencias (10 coincidencias -> max 8)
    $limited = productIds($repo->searchSuggestions('LimiteBusq', 8));
    echo '8. Limite de sugerencias (max 8): ' . (count($limited) === 8 ? 'PASS' : 'FAIL') . " (devuelve " . count($limited) . ")\n";

    // 9. SQL Injection (no rompe consulta, no devuelve todo)
    $sqliOk = true;
    foreach (["' OR 1=1 --", "' OR '1'='1", "'; DROP TABLE products; --"] as $payload) {
        try {
            $res = $repo->searchSuggestions($payload, 8);
            if ($res !== []) {
                $sqliOk = false;
            }
        } catch (\Throwable $e) {
            $sqliOk = false;
        }
    }
    try {
        $res = $repo->paginateCatalog(page: 1, perPage: 12, search: "' OR 1=1 --");
        if ((int) $res['meta']['total'] !== 0) {
            $sqliOk = false;
        }
    } catch (\Throwable $e) {
        $sqliOk = false;
    }
    echo '9. SQL Injection: ' . ($sqliOk ? 'PASS' : 'FAIL') . "\n";

    // 10. XSS (se trata como texto, sin errores)
    $xssOk = true;
    foreach (['<script>alert("xss")</script>', '<img src=x onerror=alert(1)>', '<b>martillo</b>'] as $payload) {
        try {
            $res = $repo->searchSuggestions($payload, 8);
            if ($res !== []) {
                $xssOk = false;
            }
        } catch (\Throwable $e) {
            $xssOk = false;
        }
    }
    try {
        $res = $repo->paginateCatalog(page: 1, perPage: 12, search: '<b>martillo</b>');
        if ((int) $res['meta']['total'] !== 0) {
            $xssOk = false;
        }
    } catch (\Throwable $e) {
        $xssOk = false;
    }
    echo '10. XSS tratado como texto: ' . ($xssOk ? 'PASS' : 'FAIL') . "\n";

    // 11. Busqueda excesivamente larga -> rechazada por el endpoint
    $long = str_repeat('a', 500);
    ob_start();
    $c = controllerWith(['q' => $long]);
    $c->suggestions();
    $jsonLong = ob_get_clean();
    $parsedLong = json_decode($jsonLong, true);
    $longRejected = is_array($parsedLong)
        && ($parsedLong['success'] ?? true) === false
        && !empty($parsedLong['errors']);
    echo '11. Busqueda excesivamente larga rechazada: ' . ($longRejected ? 'PASS' : 'FAIL') . "\n";

    // 12. Endpoint devuelve el formato JSON correcto
    ob_start();
    $c = controllerWith(['q' => 'mar']);
    $c->suggestions();
    $jsonSuggest = ob_get_clean();
    $parsed = json_decode($jsonSuggest, true);
    $formatOk = is_array($parsed)
        && array_key_exists('success', $parsed)
        && array_key_exists('message', $parsed)
        && array_key_exists('data', $parsed)
        && array_key_exists('errors', $parsed)
        && array_key_exists('meta', $parsed)
        && ($parsed['success'] ?? false) === true
        && is_array($parsed['data'])
        && count($parsed['data']) > 0
        && array_key_exists('product_id', $parsed['data'][0])
        && array_key_exists('name', $parsed['data'][0])
        && array_key_exists('image', $parsed['data'][0]);
    echo '12. Endpoint JSON formato correcto: ' . ($formatOk ? 'PASS' : 'FAIL') . "\n";

    // 13. Busqueda en catálogo conserva el termino
    ob_start();
    $c = controllerWith(['search' => 'martillo']);
    $c->catalog();
    $htmlResults = ob_get_clean();
    $termOk = str_contains($htmlResults, 'Productos') && str_contains($htmlResults, 'martillo');
    echo '13. Busqueda conserva el termino: ' . ($termOk ? 'PASS' : 'FAIL') . "\n";

    // 14. Busqueda sin resultados muestra estado vacio (no 404)
    ob_start();
    $c = controllerWith(['search' => 'zzzznada_no_existe']);
    $c->catalog();
    $htmlEmpty = ob_get_clean();
    $emptyOk = str_contains($htmlEmpty, 'No encontramos productos para')
        && str_contains($htmlEmpty, 'zzzznada_no_existe');
    echo '14. Sin resultados muestra estado vacio: ' . ($emptyOk ? 'PASS' : 'FAIL') . "\n";

    // 15. (extra) Prioridad de prefijo en sugerencias: "Zeta Prefijo" antes de "Omega Zeta Oeste"
    $ordered = $repo->searchSuggestions('Zeta', 8);
    $ids = productIds($ordered);
    $posPrefix = array_search($created['prefix1'], $ids, true);
    $posContains = array_search($created['contains1'], $ids, true);
    $prefixOk = $posPrefix !== false && $posContains !== false && $posPrefix < $posContains;
    echo '15. Prefijo priorizado en sugerencias: ' . ($prefixOk ? 'PASS' : 'FAIL') . "\n";

    // 16. Sugerencia seleccionada -> catálogo muestra todos los relacionados
    $suggest = productIds($repo->searchSuggestions('Martillo de Goma ' . $suffix, 8));
    $catalogRes = $repo->paginateCatalog(page: 1, perPage: 12, search: 'Martillo de Goma ' . $suffix);
    $catalogIds = productIds($catalogRes['data']);
    $relatedOk = containsId($suggest, $created['exactGoma'])
        && containsId($catalogIds, $created['exactGoma'])
        && containsId($catalogIds, $created['relatedGoma']);
    echo '16. Sugerencia -> catálogo con relacionados: ' . ($relatedOk ? 'PASS' : 'FAIL') . "\n";

    // 17. JS: autocomplete navega a catálogo, nunca a /product/{id}
    $js = file_get_contents(__DIR__ . '/../public/js/search.js');
    $jsOk = str_contains($js, "'/shop?search='")
        && !str_contains($js, "'/product/' + item.product_id")
        && !str_contains($js, '`/product/${item.product_id}`');
    echo '17. JS autocomplete -> catálogo: ' . ($jsOk ? 'PASS' : 'FAIL') . "\n";

    // 18. Catálogo trunca búsquedas excesivamente largas (sin error)
    ob_start();
    $c = controllerWith(['search' => $long]);
    $c->catalog();
    $htmlLong = ob_get_clean();
    $longOk = str_contains($htmlLong, str_repeat('a', 100))
        && !str_contains($htmlLong, str_repeat('a', 200));
    echo '18. Búsqueda larga limitada en catálogo: ' . ($longOk ? 'PASS' : 'FAIL') . "\n";

    // 19. Comillas dobles no se convierten en &quot; antes de buscar (end-to-end vía Request)
    ob_start();
    $c = controllerWith(['search' => 'Llave Inglesa 12" ' . $suffix]);
    $c->catalog();
    $htmlQuote = ob_get_clean();
    $quoteOk = str_contains($htmlQuote, 'Inglesa 12&quot;')
        && !str_contains($htmlQuote, '&amp;quot;')
        && !str_contains($htmlQuote, 'No encontramos productos para');
    echo '19. Comillas en búsqueda (valor real conservado): ' . ($quoteOk ? 'PASS' : 'FAIL') . "\n";

    // 20. Ampersand no se convierte en &amp;amp; antes de buscar
    ob_start();
    $c = controllerWith(['search' => 'Caja & Herramienta ' . $suffix]);
    $c->catalog();
    $htmlAmp = ob_get_clean();
    $ampOk = str_contains($htmlAmp, 'Caja &amp; Herramienta')
        && !str_contains($htmlAmp, '&amp;amp;')
        && !str_contains($htmlAmp, 'No encontramos productos para');
    echo '20. Ampersand en búsqueda (escape solo en presentación): ' . ($ampOk ? 'PASS' : 'FAIL') . "\n";

    // 21. Payloads XSS / SQLi / comillas vía controller: no rompen, no ejecutan, estado vacío
    $payloadOk = true;
    foreach (['<script>alert(1)</script>', '<producto>', "' OR 1=1 --", '" OR "1"="1', 'taladro 1/2"'] as $payload) {
        ob_start();
        $c = controllerWith(['search' => $payload]);
        $c->catalog();
        $htmlPayload = ob_get_clean();
        $clean = str_contains($htmlPayload, 'No encontramos productos para')
            && !str_contains($htmlPayload, 'SQLSTATE')
            && !str_contains($htmlPayload, 'PDOException')
            && !str_contains($htmlPayload, '<script>alert(1)</script>');
        if (!$clean) {
            $payloadOk = false;
        }
    }
    echo '21. XSS/SQLi/comillas vía catálogo (texto, sin ejecutar): ' . ($payloadOk ? 'PASS' : 'FAIL') . "\n";

    // 22. Sugerencias devuelven el nombre real (con comilla), nunca &quot;
    $quoteProduct = $repo->findById((int) $created['quote']);
    $suggestRaw = $quoteProduct !== null
        ? str_contains($quoteProduct->getName(), '"')
            && !str_contains($quoteProduct->getName(), '&quot;')
        : false;
    echo '22. Sugerencias nombre real (sin &quot;): ' . ($suggestRaw ? 'PASS' : 'FAIL') . "\n";

    // 23. JS: encodeURIComponent sobre el valor real, sin escape() ni entidades
    $jsOk2 = str_contains($js, 'encodeURIComponent(item.name)')
        && !str_contains($js, 'escape(')
        && !str_contains($js, '&quot;');
    echo '23. JS codifica valor real (sin doble escape): ' . ($jsOk2 ? 'PASS' : 'FAIL') . "\n";

    // ── Relevancia: familia y orden por términos ──
    $created['relExact'] = insertSearchProduct('Destornillador Phillips 6 ' . $suffix, 'active', true);
    $created['relPhillips'] = insertSearchProduct('Destornillador Phillips 4 ' . $suffix, 'active', true);
    $created['relPlano'] = insertSearchProduct('Destornillador Plano ' . $suffix, 'active', true);
    $created['relTaladro'] = insertSearchProduct('Taladro 6mm ' . $suffix, 'active', true);
    $created['relUnique'] = insertSearchProduct('Escuadra Zafiro Neo ' . $suffix, 'active', true);

    function positionOf(array $ids, int $id): ?int
    {
        $pos = array_search($id, $ids, true);
        return $pos === false ? null : $pos;
    }

    // 24. Término principal → la familia completa (relacionados, no solo el producto exacto)
    $ids24 = productIds($repo->paginateCatalog(page: 1, perPage: 12, search: 'Destornillador')['data']);
    $familyOk = containsId($ids24, $created['relExact'])
        && containsId($ids24, $created['relPhillips'])
        && containsId($ids24, $created['relPlano']);
    echo '24. Término principal agrupa familia: ' . ($familyOk ? 'PASS' : 'FAIL') . "\n";

    // 25. "Destornillador Phillips" prioriza los que cumplen todos los términos, pero permite relacionados
    $ids25 = productIds($repo->paginateCatalog(page: 1, perPage: 12, search: 'Destornillador Phillips')['data']);
    $pos25Phillips = positionOf($ids25, $created['relPhillips']);
    $pos25Plano = positionOf($ids25, $created['relPlano']);
    $prioritizeOk = $pos25Phillips !== null && $pos25Plano !== null && $pos25Phillips < $pos25Plano;
    echo '25. Todos los términos relevantes priorizados: ' . ($prioritizeOk ? 'PASS' : 'FAIL') . "\n";

    // 26. Sugerencia completa → lista relacionada, el número no domina (Taladro 6mm queda fuera)
    $ids26 = productIds($repo->paginateCatalog(page: 1, perPage: 12, search: 'Destornillador Phillips 6')['data']);
    $pos26Exact = positionOf($ids26, $created['relExact']);
    $pos26Phillips = positionOf($ids26, $created['relPhillips']);
    $relevanceOk = $pos26Exact !== null
        && $pos26Phillips !== null
        && $pos26Exact < $pos26Phillips
        && containsId($ids26, $created['relPlano'])
        && !containsId($ids26, $created['relTaladro']);
    echo '26. Números no dominan (familia relacionada, sin taladro 6mm): ' . ($relevanceOk ? 'PASS' : 'FAIL') . "\n";

    // 27. Sugerencia de producto único → el catálogo muestra ese único resultado
    $ids27 = productIds($repo->paginateCatalog(page: 1, perPage: 12, search: 'Escuadra Zafiro Neo')['data']);
    $uniqueOk = count($ids27) === 1 && containsId($ids27, $created['relUnique']);
    echo '27. Sugerencia única → catálogo con 1 resultado: ' . ($uniqueOk ? 'PASS' : 'FAIL') . " (devuelve " . count($ids27) . ")\n";

    // 28. Coincidencia parcial en cualquier posición (%mart%) → familia Martillo
    $ids28 = productIds($repo->paginateCatalog(page: 1, perPage: 12, search: 'mart')['data']);
    $partialOk = containsId($ids28, $created['visible1'])
        && containsId($ids28, $created['visible2']);
    echo '28. Coincidencia parcial en cualquier posición: ' . ($partialOk ? 'PASS' : 'FAIL') . "\n";

    echo "\n=== BUSQUEDA: 28 casos ejecutados ===\n";
} finally {
    if (!empty($createdCodes)) {
        $inList = implode(',', array_fill(0, count($createdCodes), '?'));
        $db->query(
            "DELETE FROM inventory_movements WHERE product_id IN (SELECT product_id FROM products WHERE product_code IN ($inList))",
            $createdCodes
        );
        $db->query(
            "DELETE FROM products WHERE product_code IN ($inList)",
            $createdCodes
        );
    }
}
