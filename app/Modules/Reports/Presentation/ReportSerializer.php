<?php

declare(strict_types=1);

namespace App\Modules\Reports\Presentation;

use App\Modules\Inventory\Presentation\InventorySerializer;

class ReportSerializer
{
    private array $statusBadges = [
        'pending' => 'bg-warning text-dark',
        'processing' => 'bg-info text-dark',
        'shipped' => 'bg-primary',
        'delivered' => 'bg-success',
        'cancelled' => 'bg-danger',
        'preparing' => 'bg-info text-dark',
    ];

    private array $paymentBadges = [
        'pending' => 'bg-warning text-dark',
        'paid' => 'bg-success',
        'failed' => 'bg-danger',
        'refunded' => 'bg-info',
    ];

    public function __construct(
        private InventorySerializer $inventorySerializer,
    ) {}

    public function sale(array $row): array
    {
        return [
            'sale_id' => (int) ($row['sale_id'] ?? 0),
            'sale_code' => $row['sale_code'] ?? '',
            'customer_name' => $row['customer_name'] ?? '—',
            'sale_date' => $row['sale_date'] ?? '',
            'payment_method' => $row['payment_method'] ?? '',
            'payment_status' => $row['payment_status'] ?? '',
            'payment_badge' => $this->paymentBadges[$row['payment_status'] ?? ''] ?? 'bg-secondary',
            'status' => $row['status'] ?? '',
            'status_badge' => $this->statusBadges[$row['status'] ?? ''] ?? 'bg-secondary',
            'total' => (float) ($row['total'] ?? 0),
        ];
    }

    public function topProduct(array $row): array
    {
        return [
            'product_code' => $row['product_code'] ?? '',
            'product_name' => $row['product_name'] ?? '',
            'quantity_sold' => (int) ($row['quantity_sold'] ?? 0),
            'total_sold' => (float) ($row['total_sold'] ?? 0),
        ];
    }

    public function lowStock(array $row): array
    {
        return $this->inventorySerializer->stockLevel($row);
    }

    public function movement(array $row): array
    {
        return $this->inventorySerializer->movement($row);
    }

    public function delivery(array $row): array
    {
        return [
            'delivery_id' => (int) ($row['delivery_id'] ?? 0),
            'sale_code' => $row['sale_code'] ?? '',
            'customer_name' => $row['customer_name'] ?? '—',
            'employee_name' => $row['employee_name'] ?? '—',
            'status' => $row['delivery_status'] ?? '',
            'status_badge' => $this->statusBadges[$row['delivery_status'] ?? ''] ?? 'bg-secondary',
            'shipping_date' => $row['shipping_date'] ?? null,
            'delivery_date' => $row['delivery_date'] ?? null,
        ];
    }
}
