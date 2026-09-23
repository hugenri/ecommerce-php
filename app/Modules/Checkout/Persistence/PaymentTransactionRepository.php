<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Persistence;

use App\Core\Database\Database;
use App\Modules\Checkout\Domain\PaymentTransaction;
use App\Modules\Checkout\Domain\PaymentTransactionRepositoryInterface;

/**
 * Persistencia de las transacciones de la pasarela de pago.
 * Única ubicación autorizada para el SQL de payment_transactions.
 */
class PaymentTransactionRepository implements PaymentTransactionRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function create(PaymentTransaction $transaction): PaymentTransaction
    {
        $data = $this->dehydrate($transaction);

        $id = $this->db->insert('payment_transactions', $data);

        return $transaction->withPaymentTransactionId((int) $id);
    }

    public function findByProviderOrderId(string $providerOrderId): ?PaymentTransaction
    {
        $row = $this->db->selectOne(
            'SELECT * FROM payment_transactions WHERE provider_order_id = :provider_order_id LIMIT 1',
            ['provider_order_id' => $providerOrderId]
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findBySaleId(int $saleId): ?PaymentTransaction
    {
        $row = $this->db->selectOne(
            'SELECT * FROM payment_transactions WHERE sale_id = :sale_id LIMIT 1',
            ['sale_id' => $saleId]
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function lockByProviderOrderId(string $providerOrderId): ?PaymentTransaction
    {
        $row = $this->db->selectOne(
            'SELECT * FROM payment_transactions WHERE provider_order_id = :provider_order_id LIMIT 1 FOR UPDATE',
            ['provider_order_id' => $providerOrderId]
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function update(PaymentTransaction $transaction): void
    {
        if ($transaction->getPaymentTransactionId() === null) {
            throw new \RuntimeException('No se puede actualizar una transacción sin identificador.');
        }

        $data = [
            'sale_id' => $transaction->getSaleId(),
            'payment_method' => $transaction->getPaymentMethod(),
            'provider_capture_id' => $transaction->getProviderCaptureId(),
            'status' => $transaction->getStatus(),
            'reference' => $transaction->getReference(),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->update(
            'payment_transactions',
            $data,
            ['payment_transaction_id' => $transaction->getPaymentTransactionId()]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function dehydrate(PaymentTransaction $transaction): array
    {
        return [
            'sale_id' => $transaction->getSaleId(),
            'provider' => $transaction->getProvider(),
            'payment_method' => $transaction->getPaymentMethod(),
            'provider_order_id' => $transaction->getProviderOrderId(),
            'provider_capture_id' => $transaction->getProviderCaptureId(),
            'amount' => $transaction->getAmount(),
            'currency' => $transaction->getCurrency(),
            'status' => $transaction->getStatus(),
            'reference' => $transaction->getReference(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): PaymentTransaction
    {
        return new PaymentTransaction(
            paymentTransactionId: (int) $row['payment_transaction_id'],
            saleId: $row['sale_id'] !== null ? (int) $row['sale_id'] : null,
            provider: (string) $row['provider'],
            paymentMethod: isset($row['payment_method']) && $row['payment_method'] !== null ? (string) $row['payment_method'] : null,
            providerOrderId: (string) $row['provider_order_id'],
            providerCaptureId: isset($row['provider_capture_id']) ? (string) $row['provider_capture_id'] : null,
            amount: (float) ($row['amount'] ?? 0),
            currency: (string) ($row['currency'] ?? 'MXN'),
            status: (string) ($row['status'] ?? 'completed'),
            customerId: isset($row['customer_id']) ? (int) $row['customer_id'] : null,
            addressId: isset($row['address_id']) ? (int) $row['address_id'] : null,
            items: isset($row['items_json']) ? (array) json_decode((string) $row['items_json'], true) : [],
            reference: isset($row['reference']) ? (string) $row['reference'] : null,
            createdAt: isset($row['created_at']) ? new \DateTimeImmutable((string) $row['created_at']) : null,
            updatedAt: isset($row['updated_at']) ? new \DateTimeImmutable((string) $row['updated_at']) : null,
        );
    }
}
