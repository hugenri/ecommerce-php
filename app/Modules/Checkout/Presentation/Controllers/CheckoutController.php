<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Presentation\Controllers;

use App\Core\Controller;
use App\Config\ConektaConfig;
use App\Config\PaypalConfig;
use App\Modules\Checkout\Application\CreateAddressUseCase;
use App\Modules\Checkout\Application\UseCases\ConfirmPendingSalePaymentUseCase;
use App\Modules\Checkout\Application\UseCases\CreateConektaOrderUseCase;
use App\Modules\Checkout\Application\PlaceOrderUseCase;
use App\Modules\Checkout\Domain\AddressRepositoryInterface;
use App\Modules\Checkout\Domain\BuyerInfo;
use App\Modules\Checkout\Domain\PaymentProviderRegistry;
use App\Modules\Checkout\Domain\PaymentTransactionRepositoryInterface;
use App\Modules\Checkout\Domain\Sale;
use App\Modules\Checkout\Domain\SaleRepositoryInterface;
use App\Modules\Checkout\Domain\SalePriceCalculator;
use App\Modules\Checkout\Infrastructure\Paypal\PaypalException;
use App\Modules\Customers\Application\Services\CartService;
use App\Modules\Customers\Application\UseCases\GetCustomerProfileUseCase;
use App\Http\Request;
use App\Core\Validation\Validator;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class CheckoutController extends Controller
{

    public function __construct(
        private CreateAddressUseCase $createAddress,
        private ConfirmPendingSalePaymentUseCase $confirmPendingSalePayment,
        private CreateConektaOrderUseCase $createConektaOrder,
        private PlaceOrderUseCase $placeOrder,
        private AddressRepositoryInterface $addressRepository,
        private SaleRepositoryInterface $saleRepository,
        private PaymentTransactionRepositoryInterface $transactionRepository,
        private GetCustomerProfileUseCase $getCustomerProfile,
        private PaymentProviderRegistry $paymentProviders,
        private CartService $cartService,
        private SalePriceCalculator $salePriceCalculator,
        private PaypalConfig $paypalConfig,
        private ConektaConfig $conektaConfig,
        private Validator $validator,
        private Request $request,
        SessionManagerInterface $sessionManager,
        Response $response
    ) {
        parent::__construct($sessionManager, $response);
    }

    public function checkout()
    {
        $sessionCustomer = $this->sessionManager->get('customer');
        if (!$sessionCustomer) {
            $_SESSION['redirect_after_login'] = '/checkout';
            $this->redirect('/login');
            return;
        }

        $items = $this->cartService->getItems();
        if (empty($items)) {
            $this->redirect('/cart');
            return;
        }

        $customer = $this->getCustomerProfile->execute((int) $sessionCustomer['customer_id']);
        if (!$customer) {
            $this->sessionManager->remove('customer');
            $this->redirect('/login');
            return;
        }

        $addresses = $this->addressRepository->findByCustomer($customer->getCustomerId());

        $subtotal = 0.0;
        $summaryItems = [];
        foreach ($items as $item) {
            $unitPrice = (float) ($item['price'] ?? 0.0);
            $discountPct = (float) ($item['discount'] ?? 0.0);
            $quantity = (int) ($item['quantity'] ?? 0);

            $subtotal += $this->salePriceCalculator->lineAmount($unitPrice, $discountPct, $quantity);

            $summaryItems[] = $item + [
                'discounted_price' => $this->salePriceCalculator->discountedUnitPrice($unitPrice, $discountPct),
                'line_amount' => $this->salePriceCalculator->lineAmount($unitPrice, $discountPct, $quantity),
            ];
        }

        $totals = $this->salePriceCalculator->totals($subtotal);

        $cartCount = $this->cartService->getCount();

        $selectedAddress = $this->sessionManager->get('checkout.address_id');
        $selectedPayment = $this->sessionManager->get('checkout.payment_method');
        $error = $_SESSION['checkout_error'] ?? null;
        unset($_SESSION['checkout_error']);
        $success = $_SESSION['checkout_success'] ?? null;
        unset($_SESSION['checkout_success']);

        $this->view('checkout', [
            'customer' => $customer,
            'addresses' => $addresses,
            'items' => $summaryItems,
            'subtotal' => $totals->getSubtotal(),
            'iva' => $totals->getTax(),
            'total' => $totals->getTotal(),
            'cartCount' => $cartCount,
            'selectedAddress' => $selectedAddress,
            'selectedPayment' => $selectedPayment,
            'error' => $error,
            'success' => $success,
            'providers' => $this->paymentProviders->all(),
            'paypalClientId' => $this->paypalConfig->clientId(),
            'paypalCurrency' => $this->paypalConfig->currency(),
            'conektaPublicKey' => $this->conektaConfig->publicKey(),
        ]);
    }

    public function addAddress()
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
            $_SESSION['checkout_error'] = 'Corrige los errores del formulario.';
            $_SESSION['checkout_errors'] = $errors;
            $_SESSION['checkout_old'] = $data;
            $this->redirect('/checkout');
            return;
        }

        try {
            $address = $this->createAddress->execute(
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

            $this->sessionManager->set('checkout.address_id', $address->getAddressId());
            $_SESSION['checkout_success'] = 'Dirección registrada correctamente.';
        } catch (\DomainException $e) {
            $_SESSION['checkout_error'] = $e->getMessage();
        }

        $this->redirect('/checkout');
    }

    public function placePaypalOrder()
    {
        $sessionCustomer = $this->sessionManager->get('customer');
        if (!$sessionCustomer) {
            $this->error('Inicia sesión para continuar.', 401);
            return;
        }

        $customerId = (int) $sessionCustomer['customer_id'];
        $addressId = (int) ($this->request->input('address_id') ?? 0);

        if ($addressId <= 0) {
            $this->error('Selecciona una dirección de envío.', 400);
            return;
        }

        $address = $this->addressRepository->findById($addressId);
        if (!$address || $address->getCustomerId() !== $customerId) {
            $this->error('Dirección inválida.', 400);
            return;
        }

        $cartItems = [];
        foreach ($this->cartService->getItems() as $item) {
            $cartItems[] = [
                'product_id' => (int) ($item['product_id'] ?? 0),
                'quantity' => (int) ($item['quantity'] ?? 0),
            ];
        }

        if (empty($cartItems)) {
            $this->error('El carrito está vacío.', 400);
            return;
        }

        try {
            $sale = $this->placeOrder->execute(
                customerId: $customerId,
                addressId: $addressId,
                paymentMethod: 'paypal',
                paymentStatus: 'pending',
                cartItems: $cartItems,
            );

            $saleCode = $sale->getSaleCode();

            $this->cartService->clear();
            $this->sessionManager->remove('checkout.address_id');
            $this->sessionManager->remove('checkout.payment_method');
            $this->sessionManager->remove('checkout.paypal_order');
            $this->sessionManager->set('checkout.pago_sale_code', $saleCode);

            $this->success(
                [
                    'sale_id' => $sale->getSaleId(),
                    'sale_code' => $saleCode,
                    'redirect_url' => '/pago/' . $saleCode,
                ],
                'Pedido registrado. Completa tu pago.'
            );
        } catch (\DomainException $e) {
            $this->error($e->getMessage(), 400);
        }
    }

    public function placeConektaOrder()
    {
        $sessionCustomer = $this->sessionManager->get('customer');
        if (!$sessionCustomer) {
            $this->error('Inicia sesión para continuar.', 401);
            return;
        }

        $customerId = (int) $sessionCustomer['customer_id'];
        $addressId = (int) ($this->request->input('address_id') ?? 0);

        if ($addressId <= 0) {
            $this->error('Selecciona una dirección de envío.', 400);
            return;
        }

        $address = $this->addressRepository->findById($addressId);
        if (!$address || $address->getCustomerId() !== $customerId) {
            $this->error('Dirección inválida.', 400);
            return;
        }

        $cartItems = [];
        foreach ($this->cartService->getItems() as $item) {
            $cartItems[] = [
                'product_id' => (int) ($item['product_id'] ?? 0),
                'quantity' => (int) ($item['quantity'] ?? 0),
            ];
        }

        if (empty($cartItems)) {
            $this->error('El carrito está vacío.', 400);
            return;
        }

        try {
            $sale = $this->placeOrder->execute(
                customerId: $customerId,
                addressId: $addressId,
                paymentMethod: 'conekta',
                paymentStatus: 'pending',
                cartItems: $cartItems,
            );

            $saleCode = $sale->getSaleCode();

            $this->cartService->clear();
            $this->sessionManager->remove('checkout.address_id');
            $this->sessionManager->remove('checkout.payment_method');
            $this->sessionManager->remove('checkout.conekta_order');
            $this->sessionManager->set('checkout.pago_sale_code', $saleCode);

            $this->success(
                [
                    'sale_id' => $sale->getSaleId(),
                    'sale_code' => $saleCode,
                    'redirect_url' => '/pago/' . $saleCode,
                ],
                'Pedido registrado. Completa tu pago.'
            );
        } catch (\DomainException $e) {
            $this->error($e->getMessage(), 400);
        }
    }

    public function pago(string $saleCode)
    {
        $sessionCustomer = $this->sessionManager->get('customer');
        if (!$sessionCustomer) {
            $this->redirect('/login');
            return;
        }

        $sale = $this->saleRepository->findBySaleCode($saleCode);
        if (!$sale || $sale->getCustomerId() !== (int) $sessionCustomer['customer_id']) {
            $this->redirect('/');
            return;
        }

        $details = $this->saleRepository->findDetailsBySaleId($sale->getSaleId());
        $cartCount = $this->cartService->getCount();

        $pendingReference = null;
        $pagoPaymentMethod = $sale->getPaymentMethod() === 'paypal' ? 'paypal' : 'conekta';

        $transaction = $this->transactionRepository->findBySaleId($sale->getSaleId());
        if ($transaction !== null && $transaction->getPaymentMethod() !== null) {
            $pagoPaymentMethod = $transaction->getPaymentMethod();
        }

        if (
            $sale->getPaymentMethod() !== 'paypal'
            && $sale->getPaymentStatus() !== 'paid'
            && $transaction !== null
            && $transaction->getStatus() === 'pending'
            && ($transaction->getReference() ?? '') !== ''
        ) {
            $pendingReference = $transaction->getReference();
        }

        $pagoConektaOrderId = $this->sessionManager->get('checkout.pago_conekta_order');
        $pagoCheckoutRequestId = $this->sessionManager->get('checkout.pago_conekta_checkout_request');

        $this->view('pago', [
            'sale' => $sale,
            'details' => $details,
            'customer' => $sessionCustomer,
            'cartCount' => $cartCount,
            'conektaPublicKey' => $this->conektaConfig->publicKey(),
            'paypalClientId' => $this->paypalConfig->clientId(),
            'paypalCurrency' => $this->paypalConfig->currency(),
            'paymentMethod' => $sale->getPaymentMethod(),
            'pagoPendingReference' => $pendingReference,
            'pagoPaymentMethod' => $pagoPaymentMethod,
            'pagoConektaOrderId' => $pagoConektaOrderId,
            'pagoCheckoutRequestId' => $pagoCheckoutRequestId,
        ]);
    }

    public function pagoCreateConektaOrder(string $saleCode)
    {
        $sessionCustomer = $this->sessionManager->get('customer');
        if (!$sessionCustomer) {
            $this->error('Inicia sesión para continuar.', 401);
            return;
        }

        $sale = $this->saleRepository->findBySaleCode($saleCode);
        if (!$sale || $sale->getCustomerId() !== (int) $sessionCustomer['customer_id']) {
            $this->error('Pedido no encontrado.', 404);
            return;
        }

        $details = $this->saleRepository->findDetailsBySaleId($sale->getSaleId());
        $cartItems = [];
        foreach ($details as $detail) {
            $cartItems[] = [
                'product_id' => (int) ($detail['product_id'] ?? 0),
                'quantity' => (int) ($detail['quantity'] ?? 0),
            ];
        }

        if (empty($cartItems)) {
            $this->error('El pedido no tiene artículos para pagar.', 400);
            return;
        }

        $customer = $this->getCustomerProfile->execute((int) $sessionCustomer['customer_id']);
        if (!$customer) {
            $this->error('No se encontró el perfil del cliente.', 400);
            return;
        }

        $buyer = new BuyerInfo(
            name: $customer->getFullName(),
            email: $customer->getEmail(),
            phone: $customer->getPhone(),
        );

        try {
            $providerId = $sale->getPaymentMethod() === 'paypal' ? 'paypal' : 'conekta';

            $checkout = $this->createConektaOrder->execute(
                customerId: $sale->getCustomerId(),
                addressId: $sale->getAddressId(),
                buyer: $buyer,
                cartItems: $cartItems,
                providerId: $providerId,
            );

            $this->sessionManager->set('checkout.pago_conekta_order', $checkout->orderId());
            $this->sessionManager->set('checkout.pago_conekta_checkout_request', $checkout->checkoutRequestId());

            $this->success(
                [
                    'order_id' => $checkout->orderId(),
                    'checkout_request_id' => $checkout->checkoutRequestId(),
                ],
                'Orden de pago creada correctamente.'
            );
        } catch (\DomainException $e) {
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Confirma el pago de la venta contra la pasarela (endpoint /pago/{sale_code}/confirm).
     *
     * Responde SIEMPRE el contrato consistente de /confirm:
     *   paid    -> { success: true,  status: "paid",    ... } HTTP 200
     *   pending -> { success: true,  status: "pending", ... } HTTP 200
     *   failed  -> { success: false, status: "failed",  ... } HTTP 400/404/409
     *
     * Los casos no confirmables (rechazo, venta cancelada/inexistente, validación
     * de monto/moneda, error de la pasarela) se traducen aquí a la variante
     * 'failed' con un mensaje seguro para el cliente; el detalle se conserva en
     * los logs. El frontend nunca decide por excepciones: decide por data.status
     * y la UI jamás muestra "Pago recibido" con un status distinto de 'paid'.
     */
    public function pagoConfirmConektaPayment(string $saleCode)
    {
        $sessionCustomer = $this->sessionManager->get('customer');
        if (!$sessionCustomer) {
            $this->confirmFailed($saleCode, null, 401, 'Inicia sesión para continuar.');
            return;
        }

        $sale = $this->saleRepository->findBySaleCode($saleCode);
        if (!$sale || $sale->getCustomerId() !== (int) $sessionCustomer['customer_id']) {
            $this->confirmFailed($saleCode, null, 404, 'Pedido no encontrado.');
            return;
        }

        $orderId = (string) ($this->request->input('order_id') ?? '');
        if ($orderId === '') {
            $this->confirmFailed($saleCode, $sale, 400, 'Solicitud inválida.');
            return;
        }

        try {
            $isPaypal = $sale->getPaymentMethod() === 'paypal';

            $result = $this->confirmPendingSalePayment->execute(
                customerId: $sale->getCustomerId(),
                saleId: $sale->getSaleId(),
                orderId: $orderId,
                providerId: $isPaypal ? 'paypal' : 'conekta',
                deductStock: $isPaypal,
            );
        } catch (\DomainException $exception) {
            // Rechazo esperado de dominio (venta inexistente/cancelada/vencida,
            // estado negativo de la pasarela, monto/moneda): = failed.
            error_log(sprintf(
                '[pago/confirm] Confirmación rechazada: sale_code=%s, error=%s',
                $saleCode,
                $exception->getMessage(),
            ));

            $statusCode = ($exception->getCode() >= 400 && $exception->getCode() < 500)
                ? $exception->getCode()
                : 400;

            $this->confirmFailed($saleCode, $sale, $statusCode);
            return;
        } catch (\Throwable $exception) {
            // Error técnico de la pasarela (PayPal/Conekta) no esperado: nunca
            // se reporta como pago recibido; se traduce a failed con 4xx.
            error_log(sprintf(
                '[pago/confirm] Error técnico al confirmar: sale_code=%s, error=%s',
                $saleCode,
                $exception->getMessage(),
            ));

            $statusCode = 400;
            if ($exception instanceof PaypalException && $exception->getStatusCode() >= 400 && $exception->getStatusCode() < 500) {
                $statusCode = $exception->getStatusCode();
            }

            $this->confirmFailed($saleCode, $sale, $statusCode);
            return;
        }

        if ($result['status'] === 'paid') {
            $this->confirmPaid($sale, $result);
            return;
        }

        $this->confirmPending($sale, $result);
    }

    private function confirmPaid(Sale $sale, array $result): void
    {
        $this->sessionManager->remove('checkout.pago_conekta_order');
        $this->sessionManager->remove('checkout.pago_conekta_checkout_request');

        $this->json([
            'success' => true,
            'status' => 'paid',
            'message' => 'Pago recibido.',
            'data' => $this->confirmationData('paid', $sale, $result),
            'errors' => null,
            'meta' => [],
        ], 200);
    }

    private function confirmPending(Sale $sale, array $result): void
    {
        $this->json([
            'success' => true,
            'status' => 'pending',
            'message' => 'El pago aún está pendiente de confirmación.',
            'data' => $this->confirmationData('pending', $sale, $result),
            'errors' => null,
            'meta' => [],
        ], 200);
    }

    private function confirmFailed(string $saleCode, ?Sale $sale, int $statusCode, ?string $message = null): void
    {
        $saleId = $sale?->getSaleId();

        $this->json([
            'success' => false,
            'status' => 'failed',
            'message' => $message ?? 'El pago fue rechazado o no pudo confirmarse.',
            'data' => [
                'sale_id' => $saleId,
                'sale_code' => $saleCode,
                'status' => 'failed',
                'reference' => null,
                'payment_method' => $sale?->getPaymentMethod(),
                'redirect_url' => $saleId !== null ? '/checkout/confirmation/' . $saleId : '/',
            ],
            'errors' => null,
            'meta' => [],
        ], $statusCode);
    }

    /**
     * Payload de data para las variantes confirmables (paid/pending). Mantiene
     * las claves que consume el frontend existente (status, reference,
     * payment_method, redirect_url) dentro de data.
     */
    private function confirmationData(string $status, Sale $sale, array $result): array
    {
        return [
            'sale_id' => $sale->getSaleId(),
            'sale_code' => $sale->getSaleCode(),
            'status' => $status,
            'reference' => $result['reference'] ?? null,
            'payment_method' => $result['payment_method'] ?? $sale->getPaymentMethod(),
            'redirect_url' => '/checkout/confirmation/' . $sale->getSaleId(),
        ];
    }

    public function confirmation(int $id)
    {
        $sessionCustomer = $this->sessionManager->get('customer');
        if (!$sessionCustomer) {
            $this->redirect('/login');
            return;
        }

        $sale = $this->saleRepository->findSaleById($id);
        if (!$sale || $sale->getCustomerId() !== (int) $sessionCustomer['customer_id']) {
            $this->redirect('/');
            return;
        }

        $details = $this->saleRepository->findDetailsBySaleId($id);
        $cartCount = $this->cartService->getCount();
        $customer = $this->sessionManager->get('customer');

        $this->view('confirmation', [
            'sale' => $sale,
            'details' => $details,
            'customer' => $customer,
            'cartCount' => $cartCount,
        ]);
    }
}
