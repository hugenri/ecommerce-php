<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Presentation\Controllers;

use App\Core\Controller;
use App\Modules\Checkout\Application\Admin\ListSalesUseCase;
use App\Modules\Checkout\Application\Admin\GetSaleUseCase;
use App\Modules\Checkout\Application\Admin\UpdateSaleStatusUseCase;
use App\Modules\Checkout\Application\Admin\CancelSaleUseCase;
use App\Modules\Checkout\Presentation\SaleSerializer;
use App\Http\Request;
use App\Core\Validation\Validator;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class SalesController extends Controller
{

    public function __construct(
        private ListSalesUseCase $listSales,
        private GetSaleUseCase $getSale,
        private UpdateSaleStatusUseCase $updateSaleStatus,
        private CancelSaleUseCase $cancelSale,
        private SaleSerializer $serializer,
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
        return $this->view('sales/index', [
            'userName' => $user['name'] ?? '',
            'userEmail' => $user['email'] ?? '',
        ]);
    }

    public function data()
    {
        $page = max(1, (int) ($this->request->get('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->get('per_page') ?? 10)));
        $search = trim($this->request->get('search') ?? '');
        $sortBy = $this->request->get('sort_by') ?? 'sale_date';
        $sortDir = strtoupper($this->request->get('sort_order') ?? 'DESC');

        $filters = array_filter([
            'status' => $this->request->get('status'),
            'payment_status' => $this->request->get('payment_status'),
            'payment_method' => $this->request->get('payment_method'),
            'date_from' => $this->request->get('date_from'),
            'date_to' => $this->request->get('date_to'),
        ], fn($v) => $v !== null && $v !== '');

        $result = $this->listSales->execute(
            page: $page,
            perPage: $perPage,
            search: $search,
            sortBy: $sortBy,
            sortDir: $sortDir,
            filters: $filters,
        );

        $statusBadges = [
            'pending' => 'bg-warning text-dark',
            'processing' => 'bg-info text-dark',
            'shipped' => 'bg-primary',
            'delivered' => 'bg-success',
            'cancelled' => 'bg-danger',
        ];

        $paymentBadges = [
            'pending' => 'bg-warning text-dark',
            'paid' => 'bg-success',
            'failed' => 'bg-danger',
            'refunded' => 'bg-info',
        ];

        $data = array_map(function (array $row) use ($statusBadges, $paymentBadges) {
            return [
                'sale_id' => (int) $row['sale_id'],
                'sale_code' => $row['sale_code'],
                'customer_name' => $row['customer_name'] ?? '—',
                'customer_email' => $row['customer_email'] ?? '—',
                'sale_date' => $row['sale_date'] ?? '',
                'total' => (float) ($row['total'] ?? 0),
                'payment_method' => $row['payment_method'] ?? '',
                'payment_status' => $row['payment_status'] ?? '',
                'payment_badge' => $paymentBadges[$row['payment_status'] ?? ''] ?? 'bg-secondary',
                'status' => $row['status'] ?? '',
                'status_badge' => $statusBadges[$row['status'] ?? ''] ?? 'bg-secondary',
            ];
        }, $result['data']);

        return $this->success($data, 'Lista de ventas', 200, $result['meta']);
    }

    public function show(int $id)
    {
        $result = $this->getSale->execute($id);
        if (!$result) {
            return $this->notFound('Venta no encontrada.');
        }

        $sale = $this->serializer->toArray($result['sale']);

        $details = array_map(
            fn(array $d) => $this->serializer->detailToArray($d),
            $result['details']
        );

        $delivery = $this->serializer->deliveryToArray($result['delivery']);
        $address = $this->serializer->addressToArray($result['address']);

        return $this->success([
            'sale' => $sale,
            'details' => $details,
            'delivery' => $delivery,
            'address' => $address,
        ]);
    }

    public function updateStatus(int $id)
    {
        $data = $this->request->post();
        $status = $data['status'] ?? '';

        $errors = $this->validator->validate($data, [
            'status' => 'required',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        try {
            $this->updateSaleStatus->execute($id, $status);
            return $this->success(null, 'Estado actualizado correctamente.');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage());
        }
    }

    public function cancel(int $id)
    {
        $user = $this->sessionManager->get('user');
        $userId = (int) ($user['user_id'] ?? 0);

        try {
            $this->cancelSale->execute($id, $userId ?: null);
            return $this->success(null, 'Pedido cancelado y stock restaurado.');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage());
        }
    }
}
