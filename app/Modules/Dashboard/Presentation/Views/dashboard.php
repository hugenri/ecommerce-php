<?php
$pageTitle = 'Admin - Dashboard';
$pageActive = 'dashboard';
$topbarTitle = 'Dashboard';

ob_start();
?>
<!-- KPI cards -->
<?php
$kpiCards = [
    ['icon' => 'bi-cash-coin', 'color' => 'primary', 'label' => 'Ventas de hoy', 'value' => '$' . number_format($summary['sales_today'], 2)],
    ['icon' => 'bi-graph-up-arrow', 'color' => 'success', 'label' => 'Ventas del mes', 'value' => '$' . number_format($summary['sales_month'], 2)],
    ['icon' => 'bi-hourglass-split', 'color' => 'warning', 'label' => 'Pedidos pendientes', 'value' => (string) (int) $summary['orders_pending']],
    ['icon' => 'bi-truck', 'color' => 'info', 'label' => 'Pedidos enviados', 'value' => (string) (int) $summary['orders_shipped']],
    ['icon' => 'bi-people', 'color' => 'secondary', 'label' => 'Clientes registrados', 'value' => (string) (int) $summary['customers_registered']],
    ['icon' => 'bi-box-seam', 'color' => 'primary', 'label' => 'Productos activos', 'value' => (string) (int) $summary['products_active']],
    ['icon' => 'bi-exclamation-octagon', 'color' => 'danger', 'label' => 'Productos agotados', 'value' => (string) (int) $summary['products_out_of_stock']],
    ['icon' => 'bi-percent', 'color' => 'warning', 'label' => 'Productos con descuento', 'value' => (string) (int) $summary['products_with_discount']],
];
?>
<div class="row g-3 mb-4">
    <?php foreach ($kpiCards as $card): ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card card-stat shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="bg-<?= $card['color'] ?> bg-opacity-10 rounded-3 p-3">
                                <i class="bi <?= $card['icon'] ?> fs-4 text-<?= $card['color'] ?>"></i>
                            </div>
                        </div>
                        <div>
                            <div class="text-muted small"><?= $card['label'] ?></div>
                            <div class="fs-4 fw-bold"><?= $card['value'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Ventas últimos 7 días + Productos más vendidos -->
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="bi bi-bar-chart-line me-1"></i> Ventas últimos 7 días
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th class="text-center">Pedidos</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($last7Days as $day): ?>
                                <tr>
                                    <td><?= htmlspecialchars($day['sale_date']) ?></td>
                                    <td class="text-center"><?= (int) $day['orders'] ?></td>
                                    <td class="text-end">$<?= number_format((float) $day['total'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <canvas id="salesChart" style="max-height: 180px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="bi bi-trophy me-1"></i> Productos más vendidos
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($topProducts)): ?>
                    <p class="text-muted text-center mb-0">Sin ventas registradas.</p>
                <?php else: ?>
                    <ol class="list-group list-group-numbered">
                        <?php foreach ($topProducts as $product): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="text-truncate me-2"><?= htmlspecialchars($product['product_name']) ?></span>
                                <span class="badge bg-primary rounded-pill"><?= (int) $product['quantity_sold'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Ventas por categoría + Información del sistema -->
<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="bi bi-grid me-1"></i> Ventas por categoría
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Categoría</th>
                                <th class="text-center">Unidades</th>
                                <th class="text-end">Monto vendido</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($salesByCategory)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">Sin ventas registradas.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($salesByCategory as $category): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($category['category_name']) ?></td>
                                        <td class="text-center"><?= (int) $category['quantity_sold'] ?></td>
                                        <td class="text-end">$<?= number_format((float) $category['amount_sold'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="bi bi-info-circle me-1"></i> Información del sistema
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Rol:</strong>
                            <?= htmlspecialchars(ucfirst($userRole)) ?>
                        </p>
                        <p><strong>Sesión activa:</strong> <span class="badge bg-success">Activa</span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>PHP:</strong> <?= phpversion() ?></p>
                        <p><strong>Servidor:</strong> <?= php_sapi_name() ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$viewJs = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js',
    '/public/js/dashboard/app.js?v=1',
];

$viewInlineJs = '<script>
    window.dashboardChartData = {
        labels: ' . json_encode(array_column($last7Days, 'sale_date')) . ',
        orders: ' . json_encode(array_column($last7Days, 'orders')) . ',
        totals: ' . json_encode(array_column($last7Days, 'total')) . '
    };
</script>';

$content = ob_get_clean();
require $sharedViewsPath . '/Layouts/AdminLayout.php';