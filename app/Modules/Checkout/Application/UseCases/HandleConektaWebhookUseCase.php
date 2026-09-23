<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\UseCases;

use App\Config\ConektaConfig;
use App\Modules\Checkout\Application\Admin\CancelSaleUseCase;
use App\Modules\Checkout\Application\PlaceOrderUseCase;
use App\Modules\Checkout\Application\Services\PaymentGatewayResolver;
use App\Modules\Checkout\Domain\PaymentAmountValidator;
use App\Modules\Checkout\Domain\PaymentCapture;
use App\Modules\Checkout\Domain\PaymentGatewayInterface;
use App\Modules\Checkout\Domain\PaymentTransaction;
use App\Modules\Checkout\Domain\PaymentTransactionRepositoryInterface;
use App\Modules\Checkout\Domain\SaleRepositoryInterface;
use App\Modules\Inventory\Domain\InventoryRepositoryInterface;

/**
 * Procesa las notificaciones HTTP de Conekta.
 *
 * Verifica la firma RSA del cuerpo y delega en PaymentGatewayInterface
 * (classifyEvent) la traducción del evento del proveedor a un resultado
 * abstracto. Cuando Conekta confirma el método de pago (EVENT_CHARGE_CREATED)
 * o el pago en sí (EVENT_PAID), materializa la venta a partir de la metadata
 * almacenada en la orden de Conekta (customer_id, address_id, items),
 * revalidando la orden contra Conekta (monto, moneda y estado).
 *
 * La transición de estado se aplica dentro de una única transacción de base
 * de datos: se bloquea la fila de la transacción de pago (FOR UPDATE) y solo
 * se progresa cuando su estado lo permite. El guard sale_id === null
 * garantiza que la venta se cree una sola vez aunque lleguen notificaciones
 * duplicadas o fuera de orden.
 */
class HandleConektaWebhookUseCase
{
    private const PROVIDER = 'conekta';

    private const TRANSACTION_STATUS_PENDING = 'pending';

    private const TRANSACTION_STATUS_COMPLETED = 'completed';

    private const TRANSACTION_STATUS_CANCELLED = 'cancelled';

    private const SALE_PAYMENT_PAID = 'paid';

    private const SALE_PAYMENT_PENDING = 'pending';

    private const SALE_PAYMENT_FAILED = 'failed';

    /** Estados de venta abiertos en los que la cancelación libera stock. */
    private const OPEN_SALE_STATUSES = ['pending', 'processing'];

    /** Estados terminales negativos de la orden en Conekta: no se materializa venta. */
    private const NEGATIVE_ORDER_STATUSES = ['declined', 'cancelled', 'voided', 'expired', 'failed', 'refunded'];

    public function __construct(
        private PaymentGatewayResolver $gatewayResolver,
        private PaymentTransactionRepositoryInterface $transactionRepository,
        private SaleRepositoryInterface $saleRepository,
        private PlaceOrderUseCase $placeOrder,
        private CancelSaleUseCase $cancelSale,
        private InventoryRepositoryInterface $inventoryRepository,
        private ConektaConfig $conektaConfig,
        private PaymentAmountValidator $paymentAmountValidator,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(
        string $rawBody,
        string $digestHeader,
        array $payload,
    ): void {
        if (!$this->verifySignature($rawBody, $digestHeader)) {
            throw new \RuntimeException('La firma del webhook de Conekta no es válida.');
        }

        $eventType = (string) ($payload['type'] ?? '');

        $classification = $this->gateway()->classifyEvent($eventType);

        if ($classification === null) {
            return;
        }

        $orderId = $this->extractOrderId($payload);

        if ($orderId === '') {
            return;
        }

        $metadata = $this->extractMetadata($payload);
        $customerId = (int) ($metadata['customer_id'] ?? 0);
        $addressId = (int) ($metadata['address_id'] ?? 0);
        $cartItems = $this->normalizeMetadataItems($metadata['items'] ?? []);

        $existing = $this->transactionRepository->findByProviderOrderId($orderId);

        if ($existing !== null && $this->isSettled($existing->getStatus())) {
            return;
        }

        $verifiedCapture = $this->verifyOrderForEvent($classification, $orderId, $existing?->getAmount() ?? 0);
        $verifiedStatus = $verifiedCapture?->getStatus();

        $this->applyTransition($classification, $orderId, $verifiedStatus, $verifiedCapture, $customerId, $addressId, $cartItems);
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<int, array<string, mixed>> $cartItems
     */
    private function applyTransition(
        string $classification,
        string $orderId,
        ?string $verifiedStatus,
        ?PaymentCapture $verifiedCapture,
        int $customerId,
        int $addressId,
        array $cartItems,
    ): void {
        $this->saleRepository->transaction(function () use ($classification, $orderId, $verifiedStatus, $verifiedCapture, $customerId, $addressId, $cartItems) {
            $locked = $this->transactionRepository->lockByProviderOrderId($orderId);

            if ($locked === null) {
                $locked = $this->createTransaction($orderId, $verifiedStatus);
            }

            if ($this->isSettled($locked->getStatus())) {
                return;
            }

            if ($classification === PaymentGatewayInterface::EVENT_PAID
                || $classification === PaymentGatewayInterface::EVENT_CHARGE_CREATED) {
                $this->confirmPayment($locked, $classification, $verifiedStatus, $verifiedCapture, $customerId, $addressId, $cartItems);

                return;
            }

            $this->cancelIntent($locked);
        });
    }

    /**
     * @param array<int, array<string, mixed>> $cartItems
     */
    private function createTransaction(string $orderId, ?string $verifiedStatus): PaymentTransaction
    {
        $capture = $this->gateway()->getOrder($orderId);

        $transaction = new PaymentTransaction(
            paymentTransactionId: null,
            saleId: null,
            provider: self::PROVIDER,
            paymentMethod: $capture->getPaymentMethod() ?? self::PROVIDER,
            providerOrderId: $orderId,
            providerCaptureId: null,
            amount: $capture->getAmount(),
            currency: $capture->getCurrency(),
            status: self::TRANSACTION_STATUS_PENDING,
        );

        return $this->transactionRepository->create($transaction);
    }

    /**
     * Materializa la venta a partir de la metadata de Conekta (si aún no existe)
     * y promueve su estado de pago según la orden verificada en Conekta.
     *
     * @param array<int, array<string, mixed>> $cartItems
     */
    private function confirmPayment(
        PaymentTransaction $locked,
        string $classification,
        ?string $verifiedStatus,
        ?PaymentCapture $verifiedCapture,
        int $customerId,
        int $addressId,
        array $cartItems,
    ): void {
        $salePaid = $verifiedStatus === self::SALE_PAYMENT_PAID
            || $classification === PaymentGatewayInterface::EVENT_PAID;

        $saleId = $locked->getSaleId();

        if ($saleId === null) {
            $saleId = $this->createSaleFromMetadata(
                $customerId,
                $addressId,
                $cartItems,
                $salePaid ? self::SALE_PAYMENT_PAID : self::SALE_PAYMENT_PENDING
            );
        }

        if ($salePaid) {
            if (!$this->ensureStockDeducted($saleId, $locked->getProviderOrderId())) {
                // Sin stock disponible al momento de pagar: la venta queda
                // marcada para intervención manual y NO se promueve a pagada.
                $this->transactionRepository->update($locked->withSaleId($saleId));
                return;
            }

            $this->saleRepository->updatePaymentStatus($saleId, self::SALE_PAYMENT_PAID);

            $locked = $locked->withSaleId($saleId)->withStatus(self::TRANSACTION_STATUS_COMPLETED);

            if ($verifiedCapture?->getCaptureId() !== null) {
                $locked = $locked->withProviderCaptureId($verifiedCapture->getCaptureId());
            }

            $this->transactionRepository->update($locked);

            return;
        }

        // Método de pago confirmado pero aún no pagado (OXXO): la orden queda
        // abierta hasta el evento order.paid.
        $this->transactionRepository->update($locked->withSaleId($saleId));
    }

    /**
     * Garantiza que el stock de la venta quede descontado exactamente una vez.
     *
     * Si la venta se materializó como 'paid' en el mismo evento (PlaceOrderUseCase
     * ya descuenta y registra un movimiento), o si un evento anterior ya lo hizo,
     * este método no vuelve a operar (guard de idempotencia vía hasSaleMovement).
     *
     * Verifica disponibilidad de TODOS los artículos antes de descontar para no
     * dejar un descuento parcial; si algún producto no tiene stock, marca la
     * venta con un estado visible de conflicto para intervención manual y
     * devuelve false (el webhook responde 200 sin promover el pago).
     */
    private function ensureStockDeducted(int $saleId, string $orderId): bool
    {
        if ($this->inventoryRepository->hasSaleMovement($saleId)) {
            return true;
        }

        $details = $this->saleRepository->getDetailsForCancel($saleId);

        foreach ($details as $detail) {
            $productId = (int) ($detail['product_id'] ?? 0);
            $quantity = (int) ($detail['quantity'] ?? 0);
            $available = $this->inventoryRepository->currentStock($productId);

            if ($available === null || $available < $quantity) {
                $this->markStockConflict($saleId, $orderId, $productId);
                return false;
            }
        }

        $sale = $this->saleRepository->findSaleById($saleId);

        if ($sale === null) {
            throw new \RuntimeException('No se pudo localizar la venta a la que se descuenta stock.');
        }

        $this->placeOrder->deductStockForSale($sale);

        return true;
    }

    /**
     * Marca la venta como conflicto por falta de stock y lo deja registrado
     * para intervención manual. No promueve el estado de pago ni completa la
     * transacción de pago.
     */
    private function markStockConflict(int $saleId, string $orderId, int $productId): void
    {
        $note = 'CONFLICTO_STOCK: producto ' . $productId . ' sin stock (orden ' . $orderId . ')';

        $this->saleRepository->updateSaleStatus($saleId, 'cancelled');
        $this->saleRepository->updatePaymentStatus($saleId, self::SALE_PAYMENT_FAILED);
        $this->saleRepository->updateSaleNotes($saleId, $note);

        error_log($note . ' [sale_id=' . $saleId . '] -> intervención manual requerida');
    }

    /**
     * @param array<int, array<string, mixed>> $cartItems
     */
    private function createSaleFromMetadata(
        int $customerId,
        int $addressId,
        array $cartItems,
        string $paymentStatus,
    ): int {
        if ($customerId <= 0 || $addressId <= 0) {
            throw new \RuntimeException('La metadata de la orden de Conekta no tiene cliente o dirección.');
        }

        if (empty($cartItems)) {
            throw new \RuntimeException('La metadata de la orden de Conekta no tiene artículos.');
        }

        $sale = $this->placeOrder->execute(
            customerId: $customerId,
            addressId: $addressId,
            paymentMethod: self::PROVIDER,
            paymentStatus: $paymentStatus,
            cartItems: $cartItems,
        );

        return $sale->getSaleId();
    }

    /**
     * Evento negativo (declinado, cancelado, expirado): si el intento ya
     * materializó una venta pendiente (OXXO) se cancela y se marcan ambos
     * registros; si aún no hay venta solo se cierra el intento.
     */
    private function cancelIntent(PaymentTransaction $locked): void
    {
        $saleId = $locked->getSaleId();

        if ($saleId !== null) {
            $this->cancelSaleIfOpen($saleId);
            $this->saleRepository->updatePaymentStatus($saleId, self::SALE_PAYMENT_FAILED);
        }

        $this->transactionRepository->update($locked->withStatus(self::TRANSACTION_STATUS_CANCELLED));
    }

    /**
     * Verifica contra Conekta la orden notificada antes de transicionar:
     * monto, moneda y estado coherente con el evento recibido.
     */
    private function verifyOrderForEvent(string $classification, string $orderId, float $expectedAmount): ?PaymentCapture
    {
        if ($classification !== PaymentGatewayInterface::EVENT_PAID
            && $classification !== PaymentGatewayInterface::EVENT_CHARGE_CREATED) {
            return null;
        }

        $capture = $this->gateway()->getOrder($orderId);

        if ($classification === PaymentGatewayInterface::EVENT_PAID) {
            $this->verifyPaidOrder($capture, $expectedAmount);
        } else {
            $this->verifyCreatedOrder($capture, $expectedAmount);
        }

        return $capture;
    }

    /**
     * La transacción alcanzó un resultado final (pagada o cancelada): todo
     * evento ulterior se ignora sin efectos.
     */
    private function isSettled(string $transactionStatus): bool
    {
        return $transactionStatus === self::TRANSACTION_STATUS_COMPLETED
            || $transactionStatus === self::TRANSACTION_STATUS_CANCELLED;
    }

    private function cancelSaleIfOpen(int $saleId): void
    {
        $sale = $this->saleRepository->findSaleById($saleId);

        if ($sale === null) {
            return;
        }

        if (!in_array($sale->getStatus(), self::OPEN_SALE_STATUSES, true)) {
            return;
        }

        $this->cancelSale->execute($saleId);
    }

    private function verifySignature(string $rawBody, string $digestHeader): bool
    {
        $signature = $this->extractSignature($digestHeader);

        if ($signature === '') {
            return false;
        }

        $decodedSignature = base64_decode($signature, true);

        if ($decodedSignature === false) {
            return false;
        }

        $result = openssl_verify(
            $rawBody,
            $decodedSignature,
            $this->conektaConfig->webhookPublicKey(),
            OPENSSL_ALGO_SHA256
        );

        return $result === 1;
    }

    private function extractSignature(string $digestHeader): string
    {
        $value = trim($digestHeader);

        $hashPrefix = strpos($value, 'sha256=');

        if ($hashPrefix !== false) {
            return trim(substr($value, $hashPrefix + strlen('sha256=')));
        }

        $dotPosition = strpos($value, '.');

        if ($dotPosition !== false) {
            return trim(substr($value, $dotPosition + 1));
        }

        return $value;
    }

    /**
     * Los eventos de orden traen el id en data.object.id; los eventos de
     * charge lo exponen como data.object.order_id.
     *
     * @param array<string, mixed> $payload
     */
    private function extractOrderId(array $payload): string
    {
        $object = $payload['data']['object'] ?? [];

        if (!is_array($object)) {
            return '';
        }

        return (string) ($object['order_id'] ?? ($object['id'] ?? ''));
    }

    /**
     * Extrae la metadata personalizada de la orden de Conekta.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function extractMetadata(array $payload): array
    {
        $object = $payload['data']['object'] ?? [];

        if (!is_array($object)) {
            return [];
        }

        return $object['metadata'] ?? [];
    }

    private function verifyPaidOrder(PaymentCapture $capture, float $expectedAmount): void
    {
        if ($capture->getStatus() !== PaymentGatewayInterface::EVENT_PAID) {
            throw new \RuntimeException('La orden de Conekta no está pagada.');
        }

        $this->verifyOrderIdentity($capture, $expectedAmount);
    }

    /**
     * Para charge.created la orden puede estar aún en pending_payment (OXXO)
     * o ya pagada (tarjeta); se acepta cualquier estado no terminal negativo.
     */
    private function verifyCreatedOrder(PaymentCapture $capture, float $expectedAmount): void
    {
        if (in_array($capture->getStatus(), self::NEGATIVE_ORDER_STATUSES, true)) {
            throw new \RuntimeException('La orden de Conekta está en un estado no procesable.');
        }

        $this->verifyOrderIdentity($capture, $expectedAmount);
    }

    private function verifyOrderIdentity(PaymentCapture $capture, float $expectedAmount): void
    {
        $this->paymentAmountValidator->assertMatches(
            $capture,
            $expectedAmount,
            $this->conektaConfig->currency(),
        );
    }

    private function gateway(): PaymentGatewayInterface
    {
        $gateway = $this->gatewayResolver->resolve(self::PROVIDER);

        if ($gateway === null) {
            throw new \RuntimeException('La pasarela de pago de Conekta no está disponible.');
        }

        return $gateway;
    }

    /**
     * Traduce la metadata mínima de la orden de Conekta (items[{id, qty}])
     * al formato que espera PlaceOrderUseCase (product_id/quantity).
     *
     * @param mixed $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeMetadataItems($items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $productId = (int) ($item['id'] ?? 0);
            $quantity = (int) ($item['qty'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $normalized[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
            ];
        }

        return $normalized;
    }
}
