<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Container;
use App\Core\View;
use App\Core\Database\Database;
use App\Core\Http\Response;
use App\Core\Session\Store\SessionStore;
use App\Core\SessionManager;
use App\Framework\Session\SessionManagerInterface;
use App\Framework\Session\Store\SessionStoreInterface;
use App\Http\Request;
use App\Modules\Categories\Domain\CategoryRepositoryInterface;
use App\Modules\Categories\Persistence\CategoryRepository;
use App\Modules\Customers\Domain\CartRepositoryInterface;
use App\Modules\Customers\Persistence\CartRepository;
use App\Modules\Products\Domain\ProductRepositoryInterface;
use App\Modules\Products\Persistence\ProductRepository;
use App\Modules\Store\Presentation\Controllers\CatalogController;
use App\Modules\Settings\Domain\HomeBannerRepositoryInterface;
use App\Modules\Settings\Domain\SiteSettingsRepositoryInterface;
use App\Modules\Settings\Persistence\HomeBannerRepository;
use App\Modules\Settings\Persistence\SiteSettingsRepository;
use App\Modules\Subcategories\Domain\SubcategoryRepositoryInterface;
use App\Modules\Subcategories\Persistence\SubcategoryRepository;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$container = new Container();
$container->singleton(Database::class);
$container->bind(SessionStoreInterface::class, SessionStore::class);
$container->singleton(SessionManagerInterface::class, SessionManager::class);
$container->singleton(Response::class);
$container->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
$container->bind(SubcategoryRepositoryInterface::class, SubcategoryRepository::class);
$container->bind(ProductRepositoryInterface::class, ProductRepository::class);
$container->bind(CartRepositoryInterface::class, CartRepository::class);
$container->bind(SiteSettingsRepositoryInterface::class, SiteSettingsRepository::class);
$container->bind(HomeBannerRepositoryInterface::class, HomeBannerRepository::class);

function catalogControllerWith(array $get): CatalogController
{
    global $container;
    $server = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/shop',
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_USER_AGENT' => 'php-test',
    ];
    $container->instance(Request::class, new Request($server, $get));
    $controller = $container->make(CatalogController::class);
    $controller->setContainer($container);
    return $controller;
}

function groupActiveSubcategories(): array
{
    global $container;
    $map = [];
    foreach ($container->make(SubcategoryRepositoryInterface::class)->catalogSubcategories() as $sub) {
        if ($sub->isActive()) {
            $map[(int) $sub->getCategoryId()][] = $sub;
        }
    }
    return $map;
}

function renderCatalog(array $get): string
{
    ob_start();
    catalogControllerWith($get)->catalog();
    return ob_get_clean();
}

function catalogToolbarSection(string $html): string
{
    preg_match('/<div class="catalog-toolbar[^>]*>(.*?)<\/div>/s', $html, $m);
    return $m[1] ?? '';
}

class _CatalogFrontendProbeView extends View
{
    public array $captured = [];

    public function render(string $module, string $view, array $data = []): void
    {
        $this->captured = $data;
    }
}

class _CatalogFrontendProbeController extends \App\Core\Controller
{
    public function __construct(SessionManagerInterface $sessionManager, Response $response)
    {
        parent::__construct($sessionManager, $response);
    }

    public function setProbeContainer(Container $container): void
    {
        $this->setContainer($container);
    }

    public function setProbeView(View $view): void
    {
        $this->view = $view;
    }

    public function probe(array $builder): array
    {
        $this->view($builder['view'], $builder['data'], 'Store');
        return $this->view->captured;
    }
}
$categoryRepo = $container->make(CategoryRepositoryInterface::class);

$passes = 0;
$total = 0;
$report = static function (string $label, bool $ok) use (&$passes, &$total): void {
    $total++;
    if ($ok) {
        $passes++;
    }
    echo ($ok ? 'PASS' : 'FAIL') . ' - ' . $label . "\n";
};

try {
    // ── Preparación: categoría del catálogo con subcategorías activas ──
    $subcategoryRepo = $container->make(SubcategoryRepositoryInterface::class);

    $catId = null;
    $subId = null;
    foreach ($categoryRepo->catalogCategories() as $cand) {
        foreach ($subcategoryRepo->findByCategory((int) $cand->getCategoryId()) as $sub) {
            if (!$sub->isActive()) {
                continue;
            }
            $catId = (int) $cand->getCategoryId();
            $catName = htmlspecialchars((string) $cand->getName());
            $subId = (int) $sub->getSubcategoryId();
            break 2;
        }
    }

    if ($catId === null || $subId === null) {
        echo "SKIP - sin categorias/subcategorias de catalogo disponibles\n";
        exit(0);
    }

    // ── Caso 1: catálogo general (sin filtros) ──
    $totalCats = count($categoryRepo->catalogCategories());
    $css = file_get_contents(__DIR__ . '/../public/css/catalog.css');
    $htmlAll = renderCatalog([]);
    $report('Trigger "Categorías" vive en el navbar como nav-link', str_contains($htmlAll, 'nav-link catalog-categories-trigger') && preg_match('/catalog-categories-trigger"[^>]*>\s*Categorías/', $htmlAll) === 1 && str_contains($htmlAll, 'data-catalog-categories-toggle'));
    $report('Dos triggers de categorías (navbar desktop + bottom nav móvil) apuntan al mismo panel', substr_count($htmlAll, 'data-catalog-categories-toggle') === 2 && substr_count($htmlAll, 'aria-controls="catalogCategoriesPanel"') === 2);
    $report('Panel dropdown único y oculto por defecto (hidden en el DOM)', str_contains($htmlAll, 'id="catalogCategoriesPanel"') && str_contains($htmlAll, 'class="catalog-categories-panel"') && str_contains($htmlAll, 'data-catalog-categories-panel') && str_contains($htmlAll, 'hidden'));
    $report('Ambos triggers comienzan cerrados (aria-expanded="false")', preg_match_all('/data-catalog-categories-toggle[^>]*aria-expanded="false"/', $htmlAll) === 2);
    $report('Panel se renderiza una sola vez en el DOM (partial compartido, no duplicado)', substr_count($htmlAll, 'id="catalogCategoriesPanel"') === 1 && substr_count($htmlAll, 'data-catalog-categories-panel') === 1);
    $toolbarAll = catalogToolbarSection($htmlAll);
    $report('El toolbar del catálogo NO tiene trigger de categorías', !str_contains($toolbarAll, 'data-catalog-categories') && !str_contains($toolbarAll, 'Categorías'));
    $report('El bottom nav móvil reemplazó "Catálogo" por un trigger "Categorías" anclado hacia arriba', preg_match('/<button[^>]*class="bottom-nav-item"[^>]*data-catalog-categories-toggle/', $htmlAll) === 1 && str_contains($htmlAll, 'data-catalog-categories-anchor="bottom"') && str_contains($htmlAll, 'bi bi-grid') && !preg_match('/href="\/shop"[^>]*aria-label="Catálogo"/', $htmlAll));
    $report('No hay sidebar fijo ni columna reservada (sin col-lg-3/9 ni catalog-sidebar)', !str_contains($htmlAll, 'col-lg-3') && !str_contains($htmlAll, 'col-lg-9') && !str_contains($htmlAll, 'catalog-sidebar'));
    $report('El filtro lateral antiguo (filter-sidebar) ya no existe', !str_contains($htmlAll, 'filter-sidebar') && !str_contains($htmlAll, 'filterForm'));
    $report('Catálogo general lista todas las categorías en la vista raíz', substr_count($htmlAll, 'catalog-category-link') === $totalCats);
    $report('Sin categoría consultada no hay preselección (sin estado activo)', !str_contains($htmlAll, 'aria-selected="true"') && !str_contains($htmlAll, 'catalog-category-link active'));
    $report('El nombre de categoría es botón de drill-down, no enlace', str_contains($htmlAll, 'data-catalog-drill-trigger') && !preg_match('/<a\s[^>]*class="catalog-category-link/', $htmlAll));
    $report('Categoría general sin categoría activa no resalta ninguna', !preg_match('/class="catalog-category-link active"/', $htmlAll));
    $report('En /shop general la vista raíz es visible y cada subvista está oculta', preg_match_all('/data-catalog-drill-sub="\d+"[^>]*hidden/', $htmlAll) === $totalCats && !preg_match('/data-catalog-drill-root[^>]*hidden/', $htmlAll));
    $report('Panel único drill-down: sin las columnas del diseño anterior', str_contains($htmlAll, 'data-catalog-drill-root') && !str_contains($htmlAll, 'catalog-categories-cols') && !str_contains($htmlAll, 'catalog-col-left'));
    $report('Estructura drill-down: vista raíz con lista + una subvista oculta por categoría', str_contains($htmlAll, 'data-catalog-drill-root') && str_contains($htmlAll, 'catalog-category-list') && preg_match_all('/data-catalog-drill-sub=/', $htmlAll) === $totalCats && preg_match_all('/data-catalog-drill-back/', $htmlAll) === $totalCats);
    $report('CSS drill-down: lista raíz con scroll vertical y panel único (sin columnas)', str_contains($css, 'catalog-drill-') && str_contains($css, 'overflow-y: auto') && str_contains($css, 'max-height: 340px') && !str_contains($css, 'catalog-categories-cols'));
    $report('CSS: el panel es fixed y tiene modificador para anclarse hacia arriba en el bottom nav', str_contains($css, 'position: fixed') && preg_match('/\.catalog-categories-panel--anchor-bottom\s*\{[^}]*overflow-y:\s*auto/', $css) === 1);

    $subMap = groupActiveSubcategories();
    $expectedSubLinks = 0;
    foreach ($categoryRepo->catalogCategories() as $catTree) {
        $expectedSubLinks += count($subMap[(int) $catTree->getCategoryId()] ?? []);
    }
    $report('Árbol completo: cada categoría trae "Ver todos de X" + todas sus subcategorías activas', $expectedSubLinks > 0 && substr_count($htmlAll, 'class="catalog-drill-all">') === $totalCats && substr_count($htmlAll, 'catalog-subcategory-link') === $expectedSubLinks);
    $report('Toda categoría (no solo la activa de la URL) trae subcategorías reales', $expectedSubLinks > 0 && preg_match_all('/href="\/shop\?category_id=\d+&subcategory_id=\d+"/', $htmlAll, $subLinksAll) === $expectedSubLinks);
    $report('Subcategorías uniformes: misma clase en todos los enlaces (sin active ni aria-current)', substr_count($htmlAll, 'class="catalog-subcategory-link">') === $expectedSubLinks && !preg_match('/catalog-subcategory-link\s+active/', $htmlAll) && !preg_match('/catalog-subcategory-link[^>]*aria-current/', $htmlAll));
    $report('CSS: sin regla .catalog-subcategory-link.active (todas las opciones se ven igual)', !preg_match('/\.catalog-subcategory-link\.active\s*\{/', $css));

    $herramientasId = null;
    foreach ($categoryRepo->catalogCategories() as $catTree) {
        if ($catTree->getName() === 'Herramientas Manuales') {
            $herramientasId = (int) $catTree->getCategoryId();
            break;
        }
    }
    if ($herramientasId !== null) {
        $knownOk = true;
        foreach (['Destornilladores', 'Llaves', 'Martillos'] as $knownName) {
            if (!preg_match('/catalog-subcategory-link[^>]*>\s*' . $knownName . '\s*<\/a>/', $htmlAll)) {
                $knownOk = false;
                break;
            }
        }
        $report('Caso conocido: Herramientas Manuales lista Destornilladores, Llaves y Martillos', $knownOk);
    } else {
        echo "NOTE - 'Herramientas Manuales' no está entre las categorías del catálogo en esta DB (caso conocido no verificable)\n";
    }

    // ── Caso 2: categoría con subcategoría activa ──
    $html = renderCatalog(['category_id' => (string) $catId, 'subcategory_id' => (string) $subId]);

    $report('Con category_id activo el panel NO preselecciona ningún drill (estado neutro)', !preg_match('/data-catalog-drill-trigger="' . $catId . '"[^>]*aria-expanded="true"/', $html) && !str_contains($html, 'catalog-category-link active'));
    $report('Con category_id activo la vista raíz es visible y las subvistas están ocultas', preg_match_all('/data-catalog-drill-sub="\d+"[^>]*hidden/', $html) === $totalCats && !preg_match('/data-catalog-drill-root[^>]*hidden/', $html));
    $report('El nombre de categoría ya NO enlaza a /shop (navega dentro del panel)', !preg_match('/<a\s[^>]*class="catalog-category-link/', $html));
    $report('Cada categoría liga su drill trigger con su subvista (aria-controls por id)', str_contains($html, 'data-catalog-drill-trigger="' . $catId . '"') && str_contains($html, 'id="catalogDrill-' . $catId . '"') && str_contains($html, 'aria-controls="catalogDrill-' . $catId . '"'));
    $report('"Ver todos de [Categoría]" sí navega a /shop?category_id=X', preg_match('/<a href="\/shop\?category_id=' . $catId . '"[^>]*class="catalog-drill-all/', $html) === 1);
    $report('Dropdown lista "Ver todos de [Categoría]"', preg_match('/catalog-drill-all[^>]*>[^<]*Ver todos de [^<]*<\/a>/', $html) === 1);
    $report('La subcategoría actual enlaza con su id pero NO queda resaltada en el panel', str_contains($html, 'subcategory_id=' . $subId . '"') && !str_contains($html, 'catalog-subcategory-link active') && !preg_match('/catalog-subcategory-link[^>]*aria-current="page"/', $html));
    $report('Breadcrumb Inicio > Categoría > Subcategoría', str_contains($html, '>Inicio</a>') && str_contains($html, $catName) && str_contains($html, 'aria-current="page"'));

    $report('Breadcrumb enlaza categoría activa al listado', str_contains($html, 'href="/shop?category_id=' . $catId . '"'));
    $report('Panel flotante único en el DOM y sin columna reservada en la página', str_contains($html, 'catalog-categories-panel"') && substr_count($html, 'id="catalogCategoriesPanel"') === 1 && !str_contains($html, 'col-lg-3'));
    $report('Toolbar con subcategoría activa tampoco tiene trigger duplicado', !str_contains(catalogToolbarSection($html), 'data-catalog-categories'));
    $report('Ordenar por: select con opciones y ruta preservada (category/subcategory)', str_contains($html, 'name="sort"') && str_contains($html, 'name="category_id" value="' . $catId . '"') && str_contains($html, 'name="subcategory_id" value="' . $subId . '"'));
    $report('Contador contextual (Mostrando resultados para)', str_contains($html, 'Mostrando resultados para'));
    $report('JS del dropdown declarado en el layout', str_contains($html, 'catalog-categories-dropdown.js'));
    $report('CSS del catálogo declarado en el layout', str_contains($html, 'catalog.css'));
    $report('No se filtra con el form antiguo (onchange submit del select de categorías)', !preg_match('/<select[^>]*name="category_id"[^>]*>/', $html));

    // ── Caso 3: comportamiento del JS (click-outside / Escape / selección de tabs) ──
    $js = file_get_contents(__DIR__ . '/../public/js/products/catalog-categories-dropdown.js');
    $report('JS: cierra con click fuera del panel, ignorando los propios triggers', str_contains($js, 'document.addEventListener("click"') && str_contains($js, '!panel.contains(event.target)') && str_contains($js, 'data-catalog-categories-toggle'));
    $report('JS: cierra con tecla Escape y devuelve foco al trigger activo', str_contains($js, '"Escape"') && str_contains($js, 'activeTrigger.focus()'));
    $report('JS: mantiene aria-expanded sincronizado en todos los triggers', str_contains($js, 'setAttribute("aria-expanded"') && str_contains($js, 'triggers.forEach'));
    $report('JS: usa un solo panel para múltiples triggers (navbar + bottom nav)', str_contains($js, 'querySelectorAll("[data-catalog-categories-toggle]")') && str_contains($js, 'Array.from'));
    $report('JS: posiciona el panel según el trigger activo (abajo en navbar, arriba en bottom nav)', str_contains($js, 'getBoundingClientRect()') && str_contains($js, 'dataset.catalogCategoriesAnchor === "bottom"') && str_contains($js, 'catalog-categories-panel--anchor-bottom'));
    $report('JS: clic en categoría abre su subvista sin navegar (sin redirección)', str_contains($js, '[data-catalog-drill-trigger]') && str_contains($js, 'preventDefault()') && !str_contains($js, 'window.location'));
    $report('JS: al elegir categoría muestra solo su subvista', str_contains($js, '[data-catalog-drill-trigger]') && str_contains($js, '[data-catalog-drill-sub]') && str_contains($js, 'view.hidden = view !== target'));
    $report('JS: "Regresar" restaura la vista raíz', str_contains($js, '[data-catalog-drill-back]') && str_contains($js, 'showRoot()') && str_contains($js, 'rootView.hidden = false'));
    $report('JS: abrir el panel resetea a la vista raíz (showRoot en setOpen)', str_contains($js, 'showRoot') && str_contains($js, 'sub.hidden = true') && str_contains($js, 'if (open)'));
    $report('JS: los enlaces del panel (Ver todos / subcategoría) cierran el panel', str_contains($js, 'panel.querySelectorAll("a")') && str_contains($js, 'setOpen(false)'));
    $report('JS sin .then()/.catch() ni var', !str_contains($js, '.then(') && !str_contains($js, '.catch(') && !preg_match('/\bvar\s/', $js));

    // ── Caso 4: el dropdown de categorías está en todas las páginas ──
    // 4a. Controller::view() inyecta el listado en el layout común (sin que cada
    // controller pase $categories).
    $probe = new _CatalogFrontendProbeController(
        $container->make(SessionManagerInterface::class),
        $container->make(Response::class)
    );
    $probe->setProbeContainer($container);
    $probe->setProbeView(new _CatalogFrontendProbeView());
    $probeData = $probe->probe(['view' => 'catalog', 'data' => ['pageTitle' => 'Probe']]);
    $report('Controller::view() inyecta catalogCategories para cualquier vista (layout común)', isset($probeData['catalogCategories']) && is_array($probeData['catalogCategories']));
    $mapCat = static fn(array $cats): array => array_map(
        static fn($c): string => $c->getCategoryId() . ':' . $c->getName(),
        $cats
    );
    $report('catalogCategories inyectada = misma consulta que usa el catálogo', $mapCat($probeData['catalogCategories'] ?? []) === $mapCat($categoryRepo->catalogCategories()));
    $mapSub = static fn(array $groups): array => array_map(
        static fn(array $subs): array => array_map(static fn($s): string => $s->getSubcategoryId() . ':' . $s->getName(), $subs),
        $groups
    );
    $report('Controller::view() inyecta el mapa completo subcategorías por categoría (catalogSubcategories)', isset($probeData['catalogSubcategories']) && is_array($probeData['catalogSubcategories']));
    $report('catalogSubcategories inyectada = todas las activas agrupadas por category_id', $mapSub($probeData['catalogSubcategories'] ?? []) === $mapSub(groupActiveSubcategories()));

    // 4b. Navbar en una página que NO es /shop (Inicio): trigger + panel + preselección.
    $firstCat = $categoryRepo->catalogCategories()[0];
    $firstCatId = (int) $firstCat->getCategoryId();
    $firstCatName = htmlspecialchars((string) $firstCat->getName());
    $navHomeScope = [
        'pageActive' => 'home',
        'sharedViewsPath' => __DIR__ . '/../app/Shared/Views',
        'siteSettings' => null,
        'headerCartCount' => 0,
        'headerCartCountProvided' => true,
    ];
    $navHomeScope['catalogCategories'] = $categoryRepo->catalogCategories();
    $navHomeScope['catalogSubcategories'] = groupActiveSubcategories();
    ob_start();
    extract($navHomeScope, EXTR_SKIP);
    require __DIR__ . '/../app/Shared/Views/Components/Navbar.php';
    $navbarHome = ob_get_clean();

    $report('En Inicio el navbar muestra el trigger "Categorías" (dropdown, no link plano)', str_contains($navbarHome, 'class="nav-link catalog-categories-trigger"') && str_contains($navbarHome, 'data-catalog-categories-toggle'));
    $report('En Inicio ya no existe el link plano "Catálogo" hacia /shop', !str_contains($navbarHome, '>Catálogo'));
    $report('En Inicio el navbar ya NO embebe el panel (vive en el partial compartido)', !str_contains($navbarHome, 'id="catalogCategoriesPanel"') && !str_contains($navbarHome, 'data-catalog-drill-root'));

    ob_start();
    extract($navHomeScope, EXTR_SKIP);
    require __DIR__ . '/../app/Shared/Views/Components/CategoriesDrilldownPanel.php';
    $drillHome = ob_get_clean();

    $report('En Inicio el panel drill-down existe en el partial compartido', str_contains($drillHome, 'id="catalogCategoriesPanel"') && str_contains($drillHome, 'data-catalog-drill-root'));
    $report('En Inicio el panel lista todas las categorías del catálogo', substr_count($drillHome, 'catalog-category-link') === $totalCats);
    $report('En Inicio el panel abre sin ninguna categoría preseleccionada', !str_contains($drillHome, 'catalog-category-link active') && !str_contains($drillHome, 'aria-selected="true"'));
    $report('En Inicio la vista raíz es visible y las subvistas están ocultas', preg_match_all('/data-catalog-drill-sub="\d+"[^>]*hidden/', $drillHome) === $totalCats && !preg_match('/data-catalog-drill-root[^>]*hidden/', $drillHome));
    $report('"Ver todos de [Primera]" en Inicio navega a /shop?category_id=', preg_match('/<a href="\/shop\?category_id=' . $firstCatId . '"[^>]*class="catalog-drill-all/', $drillHome) === 1);
    $report('En Inicio el dropdown muestra "Ver todos de ' . $firstCatName . '"', str_contains($drillHome, 'Ver todos de ' . $firstCatName));
    $report('En Inicio cada categoría trae sus subcategorías reales en su subvista', $expectedSubLinks > 0 && substr_count($drillHome, 'class="catalog-drill-all">') === $totalCats && substr_count($drillHome, 'catalog-subcategory-link') === $expectedSubLinks);
    $report('En Inicio la primera categoría tiene sus subcategorías junto a "Ver todos"', count($subMap[$firstCatId] ?? []) > 0 && preg_match_all('/href="\/shop\?category_id=' . $firstCatId . '&subcategory_id=\d+"/', $drillHome, $firstSubs) === count($subMap[$firstCatId] ?? []));
    $report('En Inicio no se marca aria-current (selección "actual" solo en /shop)', !str_contains($drillHome, 'aria-current="page"'));

    $bottomNavScope = [
        'sharedViewsPath' => __DIR__ . '/../app/Shared/Views',
        'pageActive' => 'home',
        'headerIsLoggedIn' => false,
        'headerCustomerName' => '',
        'csrfToken' => 'test',
    ];
    ob_start();
    extract($bottomNavScope, EXTR_SKIP);
    require __DIR__ . '/../app/Shared/Views/Components/BottomNav.php';
    $bottomNavHome = ob_get_clean();

    $report('En Inicio el bottom nav móvil tiene el trigger "Categorías" apuntando al panel compartido', str_contains($bottomNavHome, 'data-catalog-categories-toggle') && str_contains($bottomNavHome, 'aria-controls="catalogCategoriesPanel"') && str_contains($bottomNavHome, 'data-catalog-categories-anchor="bottom"') && preg_match('/bottom-nav-item"[^>]*>\s*<i class="bi bi-grid"/', $bottomNavHome) === 1);
    $report('En Inicio el bottom nav ya NO usa el link "Catálogo" hacia /shop', !str_contains($bottomNavHome, 'href="/shop"') && !preg_match('/aria-label="Catálogo"/', $bottomNavHome));

    // 4c. CSS/JS globales: el comportamiento del dropdown no depende de la página.
    $publicLayoutSrc = file_get_contents(__DIR__ . '/../app/Shared/Views/Layouts/PublicLayout.php');
    $catalogViewSrc = file_get_contents(__DIR__ . '/../app/Modules/Store/Presentation/Views/catalog.php');
    $report('catalog.css y dropdown.js declarados globalmente en PublicLayout', str_contains($publicLayoutSrc, 'public/css/catalog.css') && str_contains($publicLayoutSrc, 'catalog-categories-dropdown.js'));
    $report('PublicLayout incluye el partial compartido del panel una sola vez', substr_count($publicLayoutSrc, 'Components/CategoriesDrilldownPanel.php') === 1);
    $report('catalog.php ya no declara por duplicado el CSS/JS del dropdown', !str_contains($catalogViewSrc, 'catalog.css') && !str_contains($catalogViewSrc, 'catalog-categories-dropdown.js'));

    echo "\n=== FRONTEND CATALOGO: {$passes}/{$total} OK ===\n";
    echo "(categorías p/ aserción de drill-down: {$totalCats})\n";
} catch (\Throwable $e) {
    echo 'FAIL - excepción al renderizar catálogo: ' . $e->getMessage() . "\n";
    exit(1);
}

exit($passes === $total ? 0 : 2);