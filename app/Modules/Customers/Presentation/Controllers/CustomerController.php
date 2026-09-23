<?php

declare(strict_types=1);

namespace App\Modules\Customers\Presentation\Controllers;

use App\Core\Controller;
use App\Modules\Customers\Application\UseCases\ListCustomersUseCase;
use App\Modules\Customers\Application\UseCases\GetCustomerUseCase;
use App\Modules\Customers\Application\UseCases\SearchCustomersUseCase;
use App\Modules\Customers\Application\UseCases\ActivateCustomerUseCase;
use App\Modules\Customers\Application\UseCases\DeactivateCustomerUseCase;
use App\Modules\Customers\Presentation\CustomerSerializer;
use App\Http\Request;
use App\Core\Validation\Validator;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class CustomerController extends Controller
{

    public function __construct(
        private ListCustomersUseCase $listCustomers,
        private GetCustomerUseCase $getCustomer,
        private SearchCustomersUseCase $searchCustomers,
        private ActivateCustomerUseCase $activateCustomer,
        private DeactivateCustomerUseCase $deactivateCustomer,
        private CustomerSerializer $serializer,
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
        return $this->view('admin/index', [
            'userName' => $user['name'] ?? '',
            'userEmail' => $user['email'] ?? '',
        ]);
    }

    public function data()
    {
        $page = max(1, (int) ($this->request->get('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->get('per_page') ?? 10)));
        $filters = array_filter([
            'search' => $this->request->get('search'),
            'active' => $this->request->get('active') !== null ? (int) $this->request->get('active') : null,
            'sort_by' => $this->request->get('sort_by') ?? 'customers.name',
            'sort_order' => strtoupper($this->request->get('sort_order') ?? 'ASC'),
        ], fn($v) => $v !== null && $v !== '');

        $result = $this->listCustomers->execute(
            $page,
            $perPage,
            $filters['search'] ?? '',
            $filters['sort_by'] ?? 'customers.name',
            $filters['sort_order'] ?? 'ASC',
            $filters
        );

        return $this->success(
            array_map(fn($c) => $this->serializer->toArray($c), $result['data']),
            'Lista de clientes',
            200,
            $result['meta']
        );
    }

    public function show(int $id)
    {
        $customer = $this->getCustomer->execute($id);
        if (!$customer) return $this->notFound('Cliente no encontrado.');

        return $this->success($this->serializer->toArray($customer));
    }

    public function search()
    {
        $query = trim($this->request->get('q') ?? '');
        if (strlen($query) < 2) {
            return $this->success([], 'Búsqueda de clientes');
        }

        $results = $this->searchCustomers->execute($query);
        return $this->success(
            array_map(fn($c) => $this->serializer->toArray($c), $results)
        );
    }

    public function activate(int $id)
    {
        $customer = $this->activateCustomer->execute($id);
        if (!$customer) return $this->notFound('Cliente no encontrado.');

        return $this->success(
            ['customer' => $this->serializer->toArray($customer)],
            'Cliente activado exitosamente.'
        );
    }

    public function deactivate(int $id)
    {
        $customer = $this->deactivateCustomer->execute($id);
        if (!$customer) return $this->notFound('Cliente no encontrado.');

        return $this->success(
            ['customer' => $this->serializer->toArray($customer)],
            'Cliente desactivado exitosamente.'
        );
    }
}
