<?php

declare(strict_types=1);

namespace App\Modules\Deliveries\Presentation\Controllers;

use App\Core\Controller;
use App\Modules\Deliveries\Application\ListDeliveriesUseCase;
use App\Modules\Deliveries\Application\GetDeliveryUseCase;
use App\Modules\Deliveries\Application\SearchDeliveriesUseCase;
use App\Modules\Deliveries\Application\AssignEmployeeUseCase;
use App\Modules\Deliveries\Application\UpdateDeliveryStatusUseCase;
use App\Modules\Deliveries\Application\RegisterShippingDateUseCase;
use App\Modules\Deliveries\Application\RegisterDeliveryDateUseCase;
use App\Modules\Deliveries\Application\ListPendingDeliveriesUseCase;
use App\Modules\Deliveries\Application\ListEmployeeDeliveriesUseCase;
use App\Modules\Deliveries\Application\ListEmployeesUseCase;
use App\Modules\Deliveries\Presentation\DeliverySerializer;
use App\Http\Request;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class DeliveriesController extends Controller
{
    private const ALLOWED_STATUSES = ['pending', 'preparing', 'shipped', 'delivered', 'cancelled'];


    public function __construct(
        private ListDeliveriesUseCase $listDeliveries,
        private GetDeliveryUseCase $getDelivery,
        private SearchDeliveriesUseCase $searchDeliveries,
        private AssignEmployeeUseCase $assignEmployee,
        private UpdateDeliveryStatusUseCase $updateDeliveryStatus,
        private RegisterShippingDateUseCase $registerShippingDate,
        private RegisterDeliveryDateUseCase $registerDeliveryDate,
        private ListPendingDeliveriesUseCase $listPendingDeliveries,
        private ListEmployeeDeliveriesUseCase $listEmployeeDeliveries,
        private ListEmployeesUseCase $listEmployees,
        private DeliverySerializer $serializer,
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
            'employees' => $this->listEmployees->execute(),
        ]);
    }

    public function data()
    {
        $page = max(1, (int) ($this->request->get('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->get('per_page') ?? 10)));
        $search = trim($this->request->get('search') ?? '');

        $filters = array_filter([
            'status' => $this->request->get('status'),
            'employee_id' => $this->request->get('employee_id'),
            'date_from' => $this->request->get('date_from'),
            'date_to' => $this->request->get('date_to'),
        ], fn($v) => $v !== null && $v !== '');

        $result = $this->listDeliveries->execute(
            page: $page,
            perPage: $perPage,
            search: $search,
            filters: $filters,
        );

        $data = array_map(fn(array $row) => $this->serializer->listItem($row), $result['data']);

        return $this->success($data, 'Lista de entregas', 200, $result['meta']);
    }

    public function pending()
    {
        $page = max(1, (int) ($this->request->get('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->get('per_page') ?? 10)));

        $result = $this->listPendingDeliveries->execute($page, $perPage);

        $data = array_map(fn(array $row) => $this->serializer->listItem($row), $result['data']);

        return $this->success($data, 'Entregas pendientes', 200, $result['meta']);
    }

    public function employee(int $userId)
    {
        $page = max(1, (int) ($this->request->get('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->get('per_page') ?? 10)));

        $result = $this->listEmployeeDeliveries->execute($userId, $page, $perPage);

        $data = array_map(fn(array $row) => $this->serializer->listItem($row), $result['data']);

        return $this->success($data, 'Entregas del empleado', 200, $result['meta']);
    }

    public function search()
    {
        $term = trim($this->request->get('q') ?? '');
        if ($term === '') {
            return $this->success([], 'Sin resultados', 200, [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 10,
                'total' => 0,
            ]);
        }

        $page = max(1, (int) ($this->request->get('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->get('per_page') ?? 10)));

        $result = $this->searchDeliveries->execute($term, $page, $perPage);

        $data = array_map(fn(array $row) => $this->serializer->listItem($row), $result['data']);

        return $this->success($data, 'Resultados de búsqueda', 200, $result['meta']);
    }

    public function employees()
    {
        return $this->success($this->listEmployees->execute(), 'Empleados activos');
    }

    public function show(int $id)
    {
        $result = $this->getDelivery->execute($id);
        if (!$result) {
            return $this->notFound('Entrega no encontrada.');
        }

        return $this->success([
            'delivery' => $this->serializer->detail($result['delivery']),
            'products' => array_map(fn(array $p) => $this->serializer->product($p), $result['products']),
            'employees' => $result['employees'],
        ]);
    }

    public function assign(int $id)
    {
        $data = $this->request->post();
        $userId = (int) ($data['user_id'] ?? 0);

        if ($userId <= 0) {
            return $this->validationError(['user_id' => ['Selecciona un empleado.']]);
        }

        try {
            $this->assignEmployee->execute($id, $userId);
            return $this->success(null, 'Empleado asignado correctamente.');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage());
        }
    }

    public function updateStatus(int $id)
    {
        $data = $this->request->post();
        $status = $data['status'] ?? '';

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            return $this->validationError(['status' => ['Estado de entrega inválido.']]);
        }

        try {
            $this->updateDeliveryStatus->execute($id, $status);
            return $this->success(null, 'Estado de entrega actualizado.');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage());
        }
    }

    public function updateShippingDate(int $id)
    {
        $data = $this->request->post();
        $date = $data['shipping_date'] ?? '';

        if (empty($date)) {
            return $this->validationError(['shipping_date' => ['La fecha es requerida.']]);
        }

        try {
            $this->registerShippingDate->execute($id, $date);
            return $this->success(null, 'Fecha de envío registrada.');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage());
        }
    }

    public function updateDeliveryDate(int $id)
    {
        $data = $this->request->post();
        $date = $data['delivery_date'] ?? '';

        if (empty($date)) {
            return $this->validationError(['delivery_date' => ['La fecha es requerida.']]);
        }

        try {
            $this->registerDeliveryDate->execute($id, $date);
            return $this->success(null, 'Fecha de entrega registrada.');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage());
        }
    }
}
