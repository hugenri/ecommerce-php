<?php

declare(strict_types=1);

namespace App\Modules\Deliveries\Presentation;

class DeliverySerializer
{
    public function listItem(array $row): array
    {
        return [
            'delivery_id' => (int) $row['delivery_id'],
            'delivery_status' => $row['delivery_status'] ?? '',
            'shipping_date' => $row['shipping_date'] ?? null,
            'delivery_date' => $row['delivery_date'] ?? null,
            'sale_id' => (int) $row['sale_id'],
            'sale_code' => $row['sale_code'] ?? '',
            'sale_date' => $row['sale_date'] ?? null,
            'total' => (float) ($row['total'] ?? 0),
            'sale_status' => $row['sale_status'] ?? '',
            'payment_status' => $row['payment_status'] ?? '',
            'customer_id' => (int) ($row['customer_id'] ?? 0),
            'customer_name' => $row['customer_name'] ?? '',
            'customer_email' => $row['customer_email'] ?? '',
            'employee_id' => isset($row['employee_id']) ? (int) $row['employee_id'] : null,
            'employee_name' => $row['employee_name'] ?? null,
        ];
    }

    public function detail(array $row): array
    {
        return [
            'delivery_id' => (int) $row['delivery_id'],
            'sale_id' => (int) $row['sale_id'],
            'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
            'shipping_date' => $row['shipping_date'] ?? null,
            'delivery_date' => $row['delivery_date'] ?? null,
            'status' => $row['status'] ?? '',
            'sale_code' => $row['sale_code'] ?? '',
            'sale_date' => $row['sale_date'] ?? null,
            'subtotal' => (float) ($row['subtotal'] ?? 0),
            'tax' => (float) ($row['tax'] ?? 0),
            'total' => (float) ($row['total'] ?? 0),
            'payment_method' => $row['payment_method'] ?? '',
            'payment_status' => $row['payment_status'] ?? '',
            'sale_status' => $row['sale_status'] ?? '',
            'notes' => $row['notes'] ?? null,
            'customer' => [
                'customer_id' => (int) ($row['customer_id'] ?? 0),
                'full_name' => trim(
                    ($row['first_name'] ?? '') . ' ' . ($row['last_name_paternal'] ?? '') . ' ' . ($row['last_name_maternal'] ?? '')
                ),
                'email' => $row['email'] ?? '',
                'phone' => $row['phone'] ?? null,
            ],
            'employee' => [
                'employee_id' => isset($row['employee_id']) ? (int) $row['employee_id'] : null,
                'name' => $row['employee_name'] ?? null,
                'email' => $row['employee_email'] ?? null,
            ],
            'address' => [
                'street' => $row['street'] ?? null,
                'number' => $row['number'] ?? null,
                'neighborhood' => $row['neighborhood'] ?? null,
                'municipality' => $row['municipality'] ?? null,
                'state' => $row['state'] ?? null,
                'zip_code' => $row['zip_code'] ?? null,
                'reference' => $row['reference'] ?? null,
                'alias' => $row['alias'] ?? null,
            ],
        ];
    }

    public function product(array $row): array
    {
        return [
            'detail_id' => (int) $row['detail_id'],
            'product_id' => (int) $row['product_id'],
            'product_name' => $row['product_name'] ?? '',
            'product_code' => $row['product_code'] ?? '',
            'product_image' => $row['product_image'] ?? ($row['image'] ?? ''),
            'quantity' => (int) $row['quantity'],
            'unit_price' => (float) $row['unit_price'],
            'discount_percentage' => (float) ($row['discount_percentage'] ?? 0),
            'discounted_unit_price' => (float) $row['discounted_unit_price'],
            'subtotal' => (float) $row['subtotal'],
        ];
    }
}
