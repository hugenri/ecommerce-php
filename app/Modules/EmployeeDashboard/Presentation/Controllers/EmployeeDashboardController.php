<?php

declare(strict_types=1);

namespace App\Modules\EmployeeDashboard\Presentation\Controllers;

use App\Core\Controller;
use App\Modules\EmployeeDashboard\Application\GetEmployeeDashboardSummaryUseCase;
use App\Modules\EmployeeDashboard\Application\ListMyDeliveriesUseCase;
use App\Modules\EmployeeDashboard\Application\ListPendingDeliveriesUseCase;
use App\Modules\EmployeeDashboard\Application\TakeDeliveryUseCase;
use App\Modules\EmployeeDashboard\Application\GetDeliveryUseCase;
use App\Modules\EmployeeDashboard\Application\UpdateDeliveryStatusUseCase;
use App\Modules\EmployeeDashboard\Application\RegisterShippingDateUseCase;
use App\Modules\EmployeeDashboard\Application\RegisterDeliveryDateUseCase;
use App\Modules\EmployeeDashboard\Presentation\EmployeeDashboardSerializer;
use App\Http\Request;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class EmployeeDashboardController extends Controller
{

    public function __construct(
        private GetEmployeeDashboardSummaryUseCase $getSummary,
        private ListMyDeliveriesUseCase $listMyDeliveries,
        private ListPendingDeliveriesUseCase $listPendingDeliveries,
        private TakeDeliveryUseCase $takeDelivery,
        private GetDeliveryUseCase $getDelivery,
        private UpdateDeliveryStatusUseCase $updateDeliveryStatus,
        private RegisterShippingDateUseCase $registerShippingDate,
        private RegisterDeliveryDateUseCase $registerDeliveryDate,
        private EmployeeDashboardSerializer $serializer,
        private Request $request,
        SessionManagerInterface $sessionManager,
        Response $response
    ) {
        parent::__construct($sessionManager, $response);
    }

    public function index()
    {
        $user = $this->sessionManager->get('user');
        $userId = (int) ($user['user_id'] ?? 0);

        return $this->view('dashboard', [
            'userName' => $user['name'] ?? '',
            'userEmail' => $user['email'] ?? '',
            'userRole' => $user['role'] ?? 'employee',
            'userId' => $userId,
            'summary' => $this->getSummary->execute($userId),
        ]);
    }

    public function myDeliveries()
    {
        $user = $this->sessionManager->get('user');
        $userId = (int) ($user['user_id'] ?? 0);

        $page = max(1, (int) ($this->request->get('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->get('per_page') ?? 10)));

        $result = $this->listMyDeliveries->execute($userId, $page, $perPage);
        $data = array_map(fn(array $row) => $this->serializer->listItem($row), $result['data']);

        return $this->success($data, 'Mis entregas', 200, $result['meta']);
    }

    public function pending()
    {
        $page = max(1, (int) ($this->request->get('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->get('per_page') ?? 10)));

        $result = $this->listPendingDeliveries->execute($page, $perPage);
        $data = array_map(fn(array $row) => $this->serializer->listItem($row), $result['data']);

        return $this->success($data, 'Pedidos pendientes', 200, $result['meta']);
    }

    public function show(int $id)
    {
        $user = $this->sessionManager->get('user');
        $userId = (int) ($user['user_id'] ?? 0);

        try {
            $result = $this->getDelivery->execute($id, $userId);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage());
        }

        if (!$result) {
            return $this->notFound('Entrega no encontrada.');
        }

        return $this->success([
            'delivery' => $this->serializer->detail($result['delivery']),
            'products' => array_map(fn(array $p) => $this->serializer->product($p), $result['products']),
        ]);
    }

    public function take(int $id)
    {
        $user = $this->sessionManager->get('user');
        $userId = (int) ($user['user_id'] ?? 0);

        try {
            $this->takeDelivery->execute($id, $userId);
            return $this->success(null, 'Pedido tomado correctamente.');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage());
        }
    }

    public function updateStatus(int $id)
    {
        $user = $this->sessionManager->get('user');
        $userId = (int) ($user['user_id'] ?? 0);

        $data = $this->request->post();
        $status = $data['status'] ?? '';

        if (!in_array($status, ['pending', 'preparing', 'shipped', 'delivered'], true)) {
            return $this->validationError(['status' => ['Estado de entrega inválido.']]);
        }

        try {
            $this->updateDeliveryStatus->execute($id, $status, $userId);
            return $this->success(null, 'Estado de entrega actualizado.');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage());
        }
    }

    public function updateShippingDate(int $id)
    {
        $user = $this->sessionManager->get('user');
        $userId = (int) ($user['user_id'] ?? 0);

        $data = $this->request->post();
        $date = $data['shipping_date'] ?? '';

        if (empty($date)) {
            return $this->validationError(['shipping_date' => ['La fecha es requerida.']]);
        }

        try {
            $this->registerShippingDate->execute($id, $date, $userId);
            return $this->success(null, 'Fecha de envío registrada.');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage());
        }
    }

    public function updateDeliveryDate(int $id)
    {
        $user = $this->sessionManager->get('user');
        $userId = (int) ($user['user_id'] ?? 0);

        $data = $this->request->post();
        $date = $data['delivery_date'] ?? '';

        if (empty($date)) {
            return $this->validationError(['delivery_date' => ['La fecha es requerida.']]);
        }

        try {
            $this->registerDeliveryDate->execute($id, $date, $userId);
            return $this->success(null, 'Fecha de entrega registrada.');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage());
        }
    }
}
