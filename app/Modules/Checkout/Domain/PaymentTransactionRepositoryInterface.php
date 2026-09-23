<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain;

interface PaymentTransactionRepositoryInterface
{
    public function create(PaymentTransaction $transaction): PaymentTransaction;

    public function findByProviderOrderId(string $providerOrderId): ?PaymentTransaction;

    public function findBySaleId(int $saleId): ?PaymentTransaction;

    /** Bloquea la fila de la transacción dentro de una transacción abierta. */
    public function lockByProviderOrderId(string $providerOrderId): ?PaymentTransaction;

    public function update(PaymentTransaction $transaction): void;
}