<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain;

use RuntimeException;

/**
 * Valida que el monto y la moneda de una captura de pago correspondan al
 * valor esperado, dentro de una tolerancia pequeña.
 *
 * Se comparte entre el flujo del webhook (HandleConektaWebhookUseCase) y el
 * flujo síncrono de /pago (ConfirmPendingSalePaymentUseCase) para impedir
 * que una orden de pago de otra venta — aunque esté realmente pagada en la
 * pasarela — promueva una venta cuyo total no coincide con lo cobrado.
 */
final class PaymentAmountValidator
{
    /** Tolerancia máxima permitida entre el monto capturado y el esperado. */
    private const MAX_AMOUNT_DIFFERENCE = 0.01;

    public function assertMatches(
        PaymentCapture $capture,
        float $expectedAmount,
        string $expectedCurrency,
    ): void {
        if ($capture->getCurrency() !== $expectedCurrency) {
            throw new RuntimeException('La moneda de la orden de pago no coincide con la esperada.');
        }

        if ($expectedAmount > 0 && abs($capture->getAmount() - $expectedAmount) > self::MAX_AMOUNT_DIFFERENCE) {
            throw new RuntimeException('El monto de la orden de pago no corresponde al esperado.');
        }
    }
}