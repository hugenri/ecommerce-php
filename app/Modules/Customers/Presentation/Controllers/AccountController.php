<?php

declare(strict_types=1);

namespace App\Modules\Customers\Presentation\Controllers;

use App\Core\Controller;
use App\Config\AppConfig;
use App\Modules\Customers\Application\UseCases\GetCustomerProfileUseCase;
use App\Modules\Customers\Application\UseCases\UpdateCustomerProfileUseCase;
use App\Modules\Customers\Application\UseCases\ChangePasswordUseCase;
use App\Modules\Customers\Application\Services\CartService;
use App\Modules\Customers\Presentation\CustomerSerializer;
use App\Modules\Checkout\Application\CreateAddressUseCase;
use App\Modules\Checkout\Application\UpdateAddressUseCase;
use App\Modules\Checkout\Application\DeleteAddressUseCase;
use App\Modules\Checkout\Application\SetDefaultAddressUseCase;
use App\Modules\Checkout\Domain\AddressRepositoryInterface;
use App\Modules\Checkout\Domain\PaymentTransactionRepositoryInterface;
use App\Modules\Checkout\Domain\Sale;
use App\Modules\Checkout\Domain\SaleRepositoryInterface;
use App\Http\Request;
use App\Core\Validation\Validator;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class AccountController extends Controller
{

    public function __construct(
        private GetCustomerProfileUseCase $getCustomerProfile,
        private UpdateCustomerProfileUseCase $updateCustomerProfile,
        private ChangePasswordUseCase $changePassword,
        private CartService $cartService,
        private AppConfig $appConfig,
        private CustomerSerializer $serializer,
        private Validator $validator,
        private Request $request,
        private AddressRepositoryInterface $addressRepository,
        private SaleRepositoryInterface $saleRepository,
        private PaymentTransactionRepositoryInterface $transactionRepository,
        private CreateAddressUseCase $createAddress,
        private UpdateAddressUseCase $updateAddress,
        private DeleteAddressUseCase $deleteAddress,
        private SetDefaultAddressUseCase $setDefaultAddress,
        SessionManagerInterface $sessionManager,
        Response $response
    ) {
        parent::__construct($sessionManager, $response);
    }

    public function account()
    {
        $sessionCustomer = $this->sessionManager->get('customer');
        if (!$sessionCustomer) {
            $this->redirect('/login');
            return;
        }

        $customer = $this->getCustomerProfile->execute((int) $sessionCustomer['customer_id']);
        if (!$customer) {
            $this->sessionManager->remove('customer');
            $this->redirect('/login');
            return;
        }

        $section = $this->request->get('section', 'profile');
        $cartCount = $this->cartService->getCount();
        $customerArr = $this->serializer->toArray($customer);

        $viewData = [
            'customer' => $customerArr,
            'cartCount' => $cartCount,
            'section' => $section,
            'error' => $_SESSION['account_error'] ?? null,
            'success' => $_SESSION['account_success'] ?? null,
        ];
        unset($_SESSION['account_error'], $_SESSION['account_success']);

        if ($section === 'addresses') {
            $viewData['addresses'] = $this->addressRepository->findByCustomer($customer->getCustomerId());
        }

        if ($section === 'orders') {
            $page = max(1, (int) ($this->request->get('page') ?? 1));
            $viewData = array_merge($viewData, $this->ordersSection($customer->getCustomerId(), $page));
        }

        if ($section === 'order-detail') {
            $orderId = (int) ($this->request->get('order_id') ?? 0);
            $order = $orderId ? $this->saleRepository->findSaleById($orderId) : null;
            if (!$order || $order->getCustomerId() !== $customer->getCustomerId()) {
                $viewData['section'] = 'orders';
                $viewData['error'] = 'Pedido no encontrado.';
                $page = max(1, (int) ($this->request->get('page') ?? 1));
                $viewData = array_merge($viewData, $this->ordersSection($customer->getCustomerId(), $page));
            } else {
                $transaction = $this->transactionRepository->findBySaleId($orderId);
                $orderMethod = $order->getPaymentMethod() === 'paypal'
                    ? 'paypal'
                    : ($transaction?->getPaymentMethod() ?? $order->getPaymentMethod());
                $isCashOrSpei = $this->isCashOrSpei($orderMethod);

                $viewData['order'] = $order;
                $viewData['orderExpired'] = $isCashOrSpei
                    && $order->isPaymentExpired($this->appConfig->pendingPaymentExpiryHours());
                $viewData['orderCanComplete'] = !$isCashOrSpei
                    && $order->getPaymentStatus() === 'pending'
                    && $order->getStatus() === 'pending';
                $viewData['details'] = $this->saleRepository->findDetailsBySaleId($orderId);
                $viewData['delivery'] = $this->saleRepository->findDeliveryBySaleId($orderId);
                $viewData['paymentReference'] = $transaction?->getReference();
            }
        }

        $this->view('account', $viewData);
    }

    /**
     * Datos de la sección "Mis Pedidos": lista paginada de ventas del cliente,
     * el método granular de pago por venta y los IDs de ventas expiradas.
     *
     * La ventana de expiración SOLO aplica a pagos de efectivo (OXXO) o SPEI
     * (el cliente ya tiene su referencia). PayPal y Conekta tarjeta muestran
     * "Completar pago" sin importar antigüedad.
     *
     * @return array{
     *     orders: array<int, Sale>,
     *     meta: array<string, mixed>,
     *     expiredOrderIds: array<int, int>,
     *     orderPaymentMethods: array<int, string>,
     * }
     */
    private function ordersSection(int $customerId, int $page): array
    {
        $result = $this->saleRepository->findByCustomer($customerId, $page, 10);
        $expiryHours = $this->appConfig->pendingPaymentExpiryHours();

        $expiredOrderIds = [];
        $orderPaymentMethods = [];

        foreach ($result['data'] as $order) {
            $method = $this->orderPaymentMethod($order);
            $orderPaymentMethods[$order->getSaleId()] = $method;

            if ($this->isCashOrSpei($method) && $order->isPaymentExpired($expiryHours)) {
                $expiredOrderIds[] = $order->getSaleId();
            }
        }

        return [
            'orders' => $result['data'],
            'meta' => $result['meta'],
            'expiredOrderIds' => $expiredOrderIds,
            'orderPaymentMethods' => $orderPaymentMethods,
        ];
    }

    /**
     * Método de pago granular de la venta para la regla "Completar pago":
     * 'paypal', 'card', 'cash' o 'bank_transfer'. Las ventas creadas como
     * 'conekta' se afinan con la transacción de pago; sin transacción se
     * mantiene 'conekta' (aún sin clasificar).
     */
    private function orderPaymentMethod(Sale $sale): string
    {
        if ($sale->getPaymentMethod() === 'paypal') {
            return 'paypal';
        }

        $transactionMethod = $this->transactionRepository->findBySaleId($sale->getSaleId())?->getPaymentMethod();

        return $transactionMethod ?? $sale->getPaymentMethod();
    }

    /**
     * True si el método granular corresponde a efectivo (OXXO) o SPEI.
     */
    private function isCashOrSpei(string $paymentMethod): bool
    {
        return in_array($paymentMethod, ['cash', 'bank_transfer'], true);
    }

    public function updateProfile()
    {
        $sessionCustomer = $this->sessionManager->get('customer');
        if (!$sessionCustomer) {
            $this->redirect('/login');
            return;
        }

        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'first_name' => 'required|string|only_letters|min:2|max:100',
            'last_name_paternal' => 'required|string|only_letters|min:2|max:100',
            'last_name_maternal' => 'nullable|string|only_letters|min:2|max:100',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($this->validator->hasErrors($errors)) {
            $this->redirect('/account?section=profile');
            return;
        }

        try {
            $this->updateCustomerProfile->execute(
                customerId: (int) $sessionCustomer['customer_id'],
                firstName: $data['first_name'],
                lastNamePaternal: $data['last_name_paternal'],
                lastNameMaternal: $data['last_name_maternal'] ?? null,
                phone: $data['phone'] ?? null,
            );
            $_SESSION['account_success'] = 'Perfil actualizado correctamente.';
        } catch (\DomainException $e) {
            $_SESSION['account_error'] = $e->getMessage();
        }

        $this->redirect('/account?section=profile');
    }

    public function updateNameAjax()
    {
        $sessionCustomer = $this->sessionManager->get('customer');
        if (!$sessionCustomer) {
            $this->response->error('No autorizado.', 401);
            return;
        }

        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'first_name' => 'required|string|only_letters|min:2|max:100',
            'last_name_paternal' => 'required|string|only_letters|min:2|max:100',
            'last_name_maternal' => 'nullable|string|only_letters|min:2|max:100',
        ]);

        if ($this->validator->hasErrors($errors)) {
            $this->response->validationError($errors);
            return;
        }

        try {
            $customer = $this->getCustomerProfile->execute((int) $sessionCustomer['customer_id']);
            if (!$customer) {
                $this->response->error('Cliente no encontrado.', 404);
                return;
            }

            $this->updateCustomerProfile->execute(
                customerId: (int) $sessionCustomer['customer_id'],
                firstName: $data['first_name'],
                lastNamePaternal: $data['last_name_paternal'],
                lastNameMaternal: $data['last_name_maternal'] ?? null,
                phone: $customer->getPhone(),
            );

            $this->response->success([
                'first_name' => $data['first_name'],
                'last_name_paternal' => $data['last_name_paternal'],
                'last_name_maternal' => $data['last_name_maternal'] ?? '',
            ], 'Nombre actualizado correctamente.');
        } catch (\DomainException $e) {
            $this->response->error($e->getMessage(), 400);
        }
    }

    public function updatePhoneAjax()
    {
        $sessionCustomer = $this->sessionManager->get('customer');
        if (!$sessionCustomer) {
            $this->response->error('No autorizado.', 401);
            return;
        }

        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'phone' => 'nullable|string|max:20',
        ]);

        if ($this->validator->hasErrors($errors)) {
            $this->response->validationError($errors);
            return;
        }

        try {
            $customer = $this->getCustomerProfile->execute((int) $sessionCustomer['customer_id']);
            if (!$customer) {
                $this->response->error('Cliente no encontrado.', 404);
                return;
            }

            $this->updateCustomerProfile->execute(
                customerId: (int) $sessionCustomer['customer_id'],
                firstName: $customer->getFirstName(),
                lastNamePaternal: $customer->getLastNamePaternal(),
                lastNameMaternal: $customer->getLastNameMaternal(),
                phone: $data['phone'] ?? null,
            );

            $this->response->success([
                'phone' => $data['phone'] ?? '',
            ], 'Teléfono actualizado correctamente.');
        } catch (\DomainException $e) {
            $this->response->error($e->getMessage(), 400);
        }
    }

    public function changePassword()
    {
        $sessionCustomer = $this->sessionManager->get('customer');
        if (!$sessionCustomer) {
            $this->redirect('/login');
            return;
        }

        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'current_password' => 'required',
            'new_password' => 'required|format_password',
            'confirm_password' => 'required',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        if ($data['new_password'] !== $data['confirm_password']) {
            return $this->validationError([
                'confirm_password' => ['Las contraseñas no coinciden.'],
            ]);
        }

        try {
            $this->changePassword->execute(
                customerId: (int) $sessionCustomer['customer_id'],
                currentPassword: $data['current_password'],
                newPassword: $data['new_password'],
            );
            $_SESSION['account_success'] = 'Contraseña actualizada correctamente.';
            return $this->success(
                ['redirect' => '/account?section=password'],
                'Contraseña actualizada correctamente.'
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function addAddressFromAccount()
    {
        $sessionCustomer = $this->sessionManager->get('customer');
        if (!$sessionCustomer) {
            $this->redirect('/login');
            return;
        }

        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'street' => 'required|string|min:3|max:80',
            'number' => 'required|string|max:15',
            'neighborhood' => 'required|string|min:3|max:80',
            'municipality' => 'required|string|min:3|max:80',
            'state' => 'required|string|min:3|max:80',
            'zip_code' => 'required|string|digits:5',
            'reference' => 'nullable|string|max:150',
            'alias' => 'nullable|string|max:50',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        try {
            $this->createAddress->execute(
                customerId: (int) $sessionCustomer['customer_id'],
                street: $data['street'],
                number: $data['number'],
                neighborhood: $data['neighborhood'],
                municipality: $data['municipality'],
                state: $data['state'],
                zipCode: $data['zip_code'],
                reference: $data['reference'] ?? null,
                isDefault: isset($data['is_default']) ? true : null,
                alias: $data['alias'] ?? null,
            );
            $_SESSION['account_success'] = 'Dirección agregada correctamente.';
            return $this->success(
                ['redirect' => '/account?section=addresses'],
                'Dirección agregada correctamente.'
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function updateAddress(int $id)
    {
        $sessionCustomer = $this->sessionManager->get('customer');
        if (!$sessionCustomer) {
            $this->redirect('/login');
            return;
        }

        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'street' => 'required|string|min:3|max:80',
            'number' => 'required|string|max:15',
            'neighborhood' => 'required|string|min:3|max:80',
            'municipality' => 'required|string|min:3|max:80',
            'state' => 'required|string|min:3|max:80',
            'zip_code' => 'required|string|digits:5',
            'reference' => 'nullable|string|max:150',
            'alias' => 'nullable|string|max:50',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        try {
            $this->updateAddress->execute(
                addressId: $id,
                customerId: (int) $sessionCustomer['customer_id'],
                street: $data['street'],
                number: $data['number'],
                neighborhood: $data['neighborhood'],
                municipality: $data['municipality'],
                state: $data['state'],
                zipCode: $data['zip_code'],
                reference: $data['reference'] ?? null,
                isDefault: isset($data['is_default']) ? true : null,
                alias: $data['alias'] ?? null,
            );
            $_SESSION['account_success'] = 'Dirección actualizada correctamente.';
            return $this->success(
                ['redirect' => '/account?section=addresses'],
                'Dirección actualizada correctamente.'
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function deleteAddress(int $id)
    {
        $sessionCustomer = $this->sessionManager->get('customer');
        if (!$sessionCustomer) {
            $this->redirect('/login');
            return;
        }

        try {
            $this->deleteAddress->execute(
                addressId: $id,
                customerId: (int) $sessionCustomer['customer_id'],
            );
            $_SESSION['account_success'] = 'Dirección eliminada correctamente.';
        } catch (\DomainException $e) {
            $_SESSION['account_error'] = $e->getMessage();
        }

        $this->redirect('/account?section=addresses');
    }

    public function getEditAddressForm(int $id)
    {
        $sessionCustomer = $this->sessionManager->get('customer');
        if (!$sessionCustomer) {
            http_response_code(401);
            echo 'No autorizado.';
            return;
        }

        $address = $this->addressRepository->findById($id);
        if (!$address || $address->getCustomerId() !== (int) $sessionCustomer['customer_id']) {
            http_response_code(404);
            echo 'Dirección no encontrada.';
            return;
        }

        $this->view('account_address_edit', ['editAddress' => $address]);
    }

    public function setDefaultAddress(int $id)
    {
        $sessionCustomer = $this->sessionManager->get('customer');
        if (!$sessionCustomer) {
            $this->redirect('/login');
            return;
        }

        try {
            $this->setDefaultAddress->execute(
                addressId: $id,
                customerId: (int) $sessionCustomer['customer_id'],
            );
            $_SESSION['account_success'] = 'Dirección predeterminada actualizada.';
        } catch (\DomainException $e) {
            $_SESSION['account_error'] = $e->getMessage();
        }

        $this->redirect('/account?section=addresses');
    }
}
