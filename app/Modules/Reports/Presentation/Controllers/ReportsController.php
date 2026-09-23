<?php

declare(strict_types=1);

namespace App\Modules\Reports\Presentation\Controllers;

use App\Core\Controller;
use App\Modules\Reports\Application\SalesReportUseCase;
use App\Modules\Reports\Application\TopProductsReportUseCase;
use App\Modules\Reports\Application\LowStockReportUseCase;
use App\Modules\Reports\Application\InventoryMovementsReportUseCase;
use App\Modules\Reports\Application\DeliveriesReportUseCase;
use App\Modules\Reports\Presentation\ReportSerializer;
use App\Http\Request;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class ReportsController extends Controller
{

    public function __construct(
        private SalesReportUseCase $salesReport,
        private TopProductsReportUseCase $topProductsReport,
        private LowStockReportUseCase $lowStockReport,
        private InventoryMovementsReportUseCase $inventoryMovementsReport,
        private DeliveriesReportUseCase $deliveriesReport,
        private ReportSerializer $serializer,
        private Request $request,
        SessionManagerInterface $sessionManager,
        Response $response
    ) {
        parent::__construct($sessionManager, $response);
    }

    public function index()
    {
        $user = $this->sessionManager->get('user');

        return $this->view('index', [
            'userName' => $user['name'] ?? '',
            'userEmail' => $user['email'] ?? '',
        ]);
    }

    public function sales()
    {
        $page = max(1, (int) ($this->request->get('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->get('per_page') ?? 10)));
        $filters = $this->filters(['date_from', 'date_to', 'status', 'payment_status']);

        try {
            $result = $this->salesReport->execute($filters, $page, $perPage);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $meta = array_merge($result['meta'], ['summary' => $result['summary']]);

        return $this->success(
            array_map(fn(array $row) => $this->serializer->sale($row), $result['data']),
            'Reporte de ventas',
            200,
            $meta
        );
    }

    public function topProducts()
    {
        $page = max(1, (int) ($this->request->get('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->get('per_page') ?? 10)));
        $filters = $this->filters(['date_from', 'date_to']);

        try {
            $result = $this->topProductsReport->execute($filters, $page, $perPage);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(
            array_map(fn(array $row) => $this->serializer->topProduct($row), $result['data']),
            'Productos más vendidos',
            200,
            $result['meta']
        );
    }

    public function lowStock()
    {
        $page = max(1, (int) ($this->request->get('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->get('per_page') ?? 10)));
        $threshold = (int) ($this->request->get('threshold') ?? 10);

        $result = $this->lowStockReport->execute($threshold, $page, $perPage);

        return $this->success(
            array_map(fn(array $row) => $this->serializer->lowStock($row), $result['data']),
            'Reporte de stock bajo',
            200,
            $result['meta']
        );
    }

    public function inventory()
    {
        $page = max(1, (int) ($this->request->get('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->get('per_page') ?? 10)));
        $filters = $this->filters(['product_id', 'movement_type', 'date_from', 'date_to']);

        try {
            $result = $this->inventoryMovementsReport->execute($page, $perPage, $filters);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(
            array_map(fn(array $row) => $this->serializer->movement($row), $result['data']),
            'Reporte de movimientos de inventario',
            200,
            $result['meta']
        );
    }

    public function deliveries()
    {
        $page = max(1, (int) ($this->request->get('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->get('per_page') ?? 10)));
        $filters = $this->filters(['status', 'date_from', 'date_to']);

        try {
            $result = $this->deliveriesReport->execute($filters, $page, $perPage);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $meta = array_merge($result['meta'], ['summary' => $result['summary']]);

        return $this->success(
            array_map(fn(array $row) => $this->serializer->delivery($row), $result['data']),
            'Reporte de entregas',
            200,
            $meta
        );
    }

    private function filters(array $keys): array
    {
        $filters = [];
        foreach ($keys as $key) {
            $value = $this->request->get($key);
            if ($value !== null && $value !== '') {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }
}
