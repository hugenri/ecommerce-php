<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Controllers;

use App\Core\Controller;
use App\Modules\Inventory\Application\UseCases\ListInventoryUseCase;
use App\Modules\Inventory\Application\UseCases\AddStockUseCase;
use App\Modules\Inventory\Application\UseCases\AdjustStockUseCase;
use App\Modules\Inventory\Application\UseCases\ListInventoryMovementsUseCase;
use App\Modules\Inventory\Application\UseCases\LowStockReportUseCase;
use App\Modules\Inventory\Presentation\InventorySerializer;
use App\Http\Request;
use App\Core\Validation\Validator;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class InventoryController extends Controller
{

    public function __construct(
        private ListInventoryUseCase $listInventory,
        private AddStockUseCase $addStock,
        private AdjustStockUseCase $adjustStock,
        private ListInventoryMovementsUseCase $listMovements,
        private LowStockReportUseCase $lowStockReport,
        private InventorySerializer $serializer,
        private Validator $validator,
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
            'userId' => (int) ($user['user_id'] ?? 0),
        ]);
    }

    public function data()
    {
        $page = max(1, (int) ($this->request->get('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->get('per_page') ?? 10)));
        $search = trim((string) ($this->request->get('search') ?? ''));
        $sortBy = $this->request->get('sort_by') ?? 'name';
        $sortDir = strtoupper($this->request->get('sort_order') ?? 'ASC');

        $result = $this->listInventory->execute($page, $perPage, $search, $sortBy, $sortDir);

        return $this->success(
            array_map(fn(array $row) => $this->serializer->stockLevel($row), $result['data']),
            'Existencias actuales',
            200,
            $result['meta']
        );
    }

    public function movements()
    {
        $page = max(1, (int) ($this->request->get('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->get('per_page') ?? 10)));
        $filters = array_filter([
            'product_id' => $this->request->get('product_id') !== null && $this->request->get('product_id') !== ''
                ? (int) $this->request->get('product_id')
                : null,
            'movement_type' => $this->request->get('movement_type'),
            'user_id' => $this->request->get('user_id') !== null && $this->request->get('user_id') !== ''
                ? (int) $this->request->get('user_id')
                : null,
            'date_from' => $this->request->get('date_from'),
            'date_to' => $this->request->get('date_to'),
        ], fn($v) => $v !== null && $v !== '');

        $result = $this->listMovements->execute($page, $perPage, $filters);

        return $this->success(
            array_map(fn(array $row) => $this->serializer->movement($row), $result['data']),
            'Historial de movimientos',
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
            array_map(fn(array $row) => $this->serializer->stockLevel($row), $result['data']),
            'Reporte de stock bajo',
            200,
            $result['meta']
        );
    }

    public function store()
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'product_id' => 'required|numeric',
            'quantity' => 'required|numeric',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        $userId = $this->currentUserId();

        try {
            $movement = $this->addStock->execute(
                (int) $data['product_id'],
                (int) $data['quantity'],
                $userId,
                $data['reason'] ?? null,
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        }

        return $this->success(
            ['movement' => $this->serializer->movementToArray($movement)],
            'Entrada de stock registrada exitosamente.',
            201
        );
    }

    public function adjust()
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'product_id' => 'required|numeric',
            'stock' => 'required|numeric',
            'reason' => 'required|string|max:255',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        $userId = $this->currentUserId();

        try {
            $movement = $this->adjustStock->execute(
                (int) $data['product_id'],
                (int) $data['stock'],
                $userId,
                $data['reason'],
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        }

        return $this->success(
            ['movement' => $this->serializer->movementToArray($movement)],
            'Ajuste de inventario registrado exitosamente.'
        );
    }

    private function currentUserId(): int
    {
        $user = $this->sessionManager->get('user');
        return (int) ($user['user_id'] ?? 0);
    }
}
