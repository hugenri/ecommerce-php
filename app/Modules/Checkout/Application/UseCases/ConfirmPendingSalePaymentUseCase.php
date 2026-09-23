<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\UseCases;

use App\Config\AppConfig;
use App\Config\ConektaConfig;
use App\Config\PaypalConfig;
use App\Modules\Checkout\Application\PlaceOrderUseCase;
use App\Modules\Checkout\Application\Services\PaymentGatewayResolver;
use App\Modules\Checkout\Domain\PaymentAmountValidator;
use App\Modules\Checkout\Domain\PaymentCapture;
use App\Modules\Checkout\Domain\PaymentGatewayInterface;
use App\Modules\Checkout\Domain\PaymentTransaction;
use App\Modules\Checkout\Domain\PaymentTransactionRepositoryInterface;
use App\Modules\Checkout\Domain\Sale;
use App\Modules\Checkout\Domain\SaleRepositoryInterface;

/**
 * Confirma el pago de una orden de pago (Conekta o PayPal) para una venta
 * YA persistida.
 *
 * Cubre el flujo de /pago: el pedido se materializó como pendiente durante
 * el checkout y aquí se le vincula la transacción técnica del proveedor y se
 * le promueve el estado según el resultado real consultado contra la pasarela.
 *
 * NO se confía en el payload del navegador: la orden se re-consulta contra
 * la pasarela antes de promover (getOrder para Conekta; PayPal dispara la
 * captura con captureOrder antes de validar), validando monto, moneda y estado.
 * La idempotencia se garantiza con el guard createTransaction || isSettled
 * (misma transacción de base que el webhook): quien llega primero (confirm
 * del navegador o webhook) materializa el cambio; el segundo simplemente
 * verifica.
 *
 * Para tarjeta la venta se promueve a pagada (payment_status = paid) y la
 * transacción se completa. Para OXXO/SPEI la venta permanece pendiente con la
 * referencia (ficha OXXO o CLABE) visible; el webhook order.paid la promueve
 * a pagada y order.expired la cancela liberando stock.
 *
 * Para PayPal (captura síncrona, proveedor 'paypal') el descuento de stock se
 * ejecuta aquí al confirmar (deductStock = true), a diferencia de Conekta
 * donde el stock se descuenta en el webhook (deductStock = false por defecto).
 *
 * Contrato de fallo: los casos que NO pueden confirmarse lanzan DomainException
 * con un código HTTP que el controlador traduce a la respuesta JSON del
 * endpoint /confirm (status = failed):
 *   - 404 la venta a confirmar no existe;
 *   - 400 rechazo de la pasarela (PayPal), validación de monto/moneda;
 *   - 409 venta cancelada, venta con la ventana de pago vencida, o estados
 *     terminales de Conekta (expired/failed/cancelled).
 * Los casos confirmables devuelven el resumen con status 'paid' o 'pending'.
 */
class ConfirmPendingSalePaymentUseCase
{
    private const DEFAULT_PROVIDER = 'conekta';

    private const DEFAULT_DEDUCT_STOCK = false;

    private const TRANSACTION_STATUS_PENDING = 'pending';

    private const TRANSACTION_STATUS_COMPLETED = 'completed';

    private const TRANSACTION_STATUS_CANCELLED = 'cancelled';

    private const SALE_PAYMENT_PAID = 'paid';

    private const SALE_PAYMENT_PENDING = 'pending';

    /** Estado "pagado" reportado por la pasarela según el proveedor (normalizado a minúsculas). */
    private const GATEWAY_STATUS_PAID_CONEXTA = 'paid';

    private const GATEWAY_STATUS_PAID_PAYPAL = 'completed';

    /** Estados terminales negativos de la orden en la pasarela: no se confirma la venta. */
    private const NEGATIVE_ORDER_STATUSES = ['declined', 'cancelled', 'voided', 'expired', 'failed', 'refunded'];

    public function __construct(
        private PaymentGatewayResolver $gatewayResolver,
        private PaymentTransactionRepositoryInterface $transactionRepository,
        private SaleRepositoryInterface $saleRepository,
        private PlaceOrderUseCase $placeOrder,
        private PaymentAmountValidator $paymentAmountValidator,
        private ConektaConfig $conektaConfig,
        private PaypalConfig $paypalConfig,
        private AppConfig $appConfig,
    ) {}

    /**
     * @param int    $customerId
     * @param int    $saleId
     * @param string $orderId
     * @param string $providerId 'conekta' (default) o 'paypal'
     * @param bool   $deductStock Descuenta el stock al confirmar como pagado
     *                            (true para PayPal síncrono; false para Conekta,
     *                            cuyo stock descuenta el webhook).
     * @return array<string, mixed> Resumen para la respuesta del controlador.
     * @throws \DomainException Con código HTTP 404/400/409 cuando el pago no
     *                          puede confirmarse (el controlador responde failed).
     */
    public function execute(
        int $customerId,
        int $saleId,
        string $orderId,
        string $providerId = self::DEFAULT_PROVIDER,
        bool $deductStock = self::DEFAULT_DEDUCT_STOCK,
    ): array {
        $sale = $this->findSale($saleId);

        $this->assertSaleConfirmable($sale);

        $capture = $this->providerCapture($providerId, $orderId);

        // Los estados de la pasarela se normalizan a minúsculas: Conekta ya los
        // entrega en minúsculas, PayPal en MAYÚSCULAS (COMPLETED, DECLINED...).
        $gatewayStatus = strtolower($capture->getStatus());

        if (in_array($gatewayStatus, self::NEGATIVE_ORDER_STATUSES, true)) {
            throw new \DomainException(
                'La orden de pago no puede confirmarse en su estado actual.',
                $this->rejectedHttpCode($providerId, $gatewayStatus),
            );
        }

        $this->assertPaymentMatchesSale($sale, $capture, $orderId, $providerId);

        $paid = $gatewayStatus === $this->paidStatus($providerId);
        $reference = $capture->getReference();

        $this->saleRepository->transaction(function () use ($sale, $saleId, $orderId, $paid, $reference, $capture, $providerId, $deductStock) {
            $locked = $this->transactionRepository->lockByProviderOrderId($orderId);

            if ($locked === null) {
                $locked = $this->transactionRepository->create(
                    $this->createTransaction($orderId, $capture, $saleId, $providerId)
                );
            }

            if ($this->isSettled($locked->getStatus())) {
                return;
            }

            if ($paid) {
                $this->saleRepository->updatePaymentStatus($saleId, self::SALE_PAYMENT_PAID);

                if ($deductStock) {
                    $this->placeOrder->deductStockForSale($sale);
                }

                $this->transactionRepository->update(
                    $locked
                        ->withSaleId($saleId)
                        ->withReference($reference)
                        ->withStatus(self::TRANSACTION_STATUS_COMPLETED)
                );

                return;
            }

            // OXXO/SPEI: queda pendiente con su referencia visible.
            $this->transactionRepository->update(
                $locked
                    ->withSaleId($saleId)
                    ->withReference($reference)
            );
        });

        return [
            'sale_id' => $saleId,
            'status' => $paid ? self::SALE_PAYMENT_PAID : self::SALE_PAYMENT_PENDING,
            'reference' => $reference,
            'payment_method' => $capture->getPaymentMethod() ?? $providerId,
        ];
    }

    private function createTransaction(string $orderId, PaymentCapture $capture, int $saleId, string $providerId): PaymentTransaction
    {
        return new PaymentTransaction(
            paymentTransactionId: null,
            saleId: $saleId,
            provider: $providerId,
            paymentMethod: $capture->getPaymentMethod() ?? $providerId,
            providerOrderId: $orderId,
            providerCaptureId: $capture->getCaptureId(),
            amount: $capture->getAmount(),
            currency: $capture->getCurrency(),
            status: self::TRANSACTION_STATUS_PENDING,
        );
    }

    private function isSettled(string $transactionStatus): bool
    {
        return $transactionStatus === self::TRANSACTION_STATUS_COMPLETED
            || $transactionStatus === self::TRANSACTION_STATUS_CANCELLED;
    }

    private function paidStatus(string $providerId): string
    {
        return $providerId === 'paypal'
            ? self::GATEWAY_STATUS_PAID_PAYPAL
            : self::GATEWAY_STATUS_PAID_CONEXTA;
    }

    /**
     * Obtiene el resultado real de la pasarela para la orden técnica.
     *
     * PayPal requiere una captura explícita (POST capture) para que la orden
     * aprobada pase de APPROVED a COMPLETED: sin ella la orden nunca llega a
     * COMPLETED y la venta quedaría pendiente sin cargo. Conekta se re-consulta
     * con getOrder (la captura de tarjeta es inmediata y OXXO/SPEI son
     * pendientes promovidos por webhook).
     */
    private function providerCapture(string $providerId, string $orderId): PaymentCapture
    {
        $gateway = $this->gateway($providerId);

        return $providerId === 'paypal'
            ? $gateway->captureOrder($orderId)
            : $gateway->getOrder($orderId);
    }

    private function findSale(int $saleId): Sale
    {
        $sale = $this->saleRepository->findSaleById($saleId);

        if ($sale === null) {
            throw new \DomainException('La venta a confirmar no existe.', 404);
        }

        return $sale;
    }

    /**
     * Bloquea la confirmación de una venta que ya no es confirmable:
     * cancelada (y no pagada) o con la ventana de pago vencida.
     *
     * Estos guardas evalúan ANTES de consultar/capturar la pasarela para no
     * tomar dinero de una venta cancelada o vencida. Una venta ya pagada o
     * cuyo pago sí venció en la pasarela queda fuera: la venta pagada sigue
     * siendo idempotente y el estado terminal negativo de la pasarela se
     * rechaza en el guarda de NEGATIVE_ORDER_STATUSES.
     */
    private function assertSaleConfirmable(Sale $sale): void
    {
        if ($sale->getStatus() === 'cancelled' && $sale->getPaymentStatus() !== 'paid') {
            throw new \DomainException('La venta fue cancelada y no puede confirmarse.', 409);
        }

        if ($sale->isPaymentExpired($this->appConfig->pendingPaymentExpiryHours())) {
            throw new \DomainException('El plazo para pagar esta venta venció.', 409);
        }
    }

    /**
     * Código HTTP para un estado terminal negativo de la pasarela.
     *
     * PayPal: la orden quedó rechazada/cancelada en la captura síncrona → 400.
     * Conekta: expired/failed/cancelled son estados terminales del método de
     * pago (OXXO/SPEI/tarjeta) → 409. El resto (declined, voided, refunded)
     * se reporta como 400.
     */
    private function rejectedHttpCode(string $providerId, string $gatewayStatus): int
    {
        if ($providerId === 'conekta' && in_array($gatewayStatus, ['expired', 'failed', 'cancelled'], true)) {
            return 409;
        }

        return 400;
    }

    /**
     * Verifica que el monto y la moneda de la orden consultada en la pasarela
     * correspondan a los de la venta. Un `order_id` legítimo pero de otra
     * venta (monto distinto) no debe promover esta venta: si ocurre, se
     * registra el intento como posible fraude y se bloquea la confirmación.
     */
    private function assertPaymentMatchesSale(Sale $sale, PaymentCapture $capture, string $orderId, string $providerId): void
    {
        try {
            $this->paymentAmountValidator->assertMatches(
                $capture,
                $sale->getTotal(),
                $this->expectedCurrency($providerId),
            );
        } catch (\RuntimeException) {
            error_log(sprintf(
                'Posible fraude de pago: sale_code=%s, order_id=%s, total esperado=%.2f %s, monto capturado=%.2f %s',
                $sale->getSaleCode(),
                $orderId,
                $sale->getTotal(),
                $this->expectedCurrency($providerId),
                $capture->getAmount(),
                $capture->getCurrency(),
            ));

            throw new \DomainException('El pago no corresponde al monto de esta venta.', 400);
        }
    }

    private function expectedCurrency(string $providerId): string
    {
        return $providerId === 'paypal'
            ? $this->paypalConfig->currency()
            : $this->conektaConfig->currency();
    }

    private function gateway(string $providerId): PaymentGatewayInterface
    {
        $gateway = $this->gatewayResolver->resolve($providerId);

        if ($gateway === null) {
            throw new \RuntimeException("La pasarela de pago de {$providerId} no está disponible.");
        }

        return $gateway;
    }
}