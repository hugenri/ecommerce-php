<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Presentation;

use App\Modules\Checkout\Domain\Sale;
use App\Modules\Checkout\Domain\SaleDetail;
use App\Modules\Checkout\Domain\Delivery;
use App\Modules\Checkout\Domain\Address;

class SaleSerializer
{
    public function toArray(Sale $sale, array $extra = []): array
    {
        return array_merge([
            'sale_id' => $sale->getSaleId(),
            'sale_code' => $sale->getSaleCode(),
            'customer_id' => $sale->getCustomerId(),
            'address_id' => $sale->getAddressId(),
            'payment_method' => $sale->getPaymentMethod(),
            'payment_status' => $sale->getPaymentStatus(),
            'sale_date' => $sale->getSaleDate()?->format('Y-m-d H:i:s'),
            'subtotal' => $sale->getSubtotal(),
            'tax' => $sale->getTax(),
            'total' => $sale->getTotal(),
            'status' => $sale->getStatus(),
            'notes' => $sale->getNotes(),
            'created_at' => $sale->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $sale->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ], $extra);
    }

    public function detailToArray(array $detail): array
    {
        return [
            'detail_id' => (int) ($detail['detail_id'] ?? 0),
            'product_id' => (int) ($detail['product_id'] ?? 0),
            'product_name' => $detail['product_name'] ?? '',
            'product_code' => $detail['product_code'] ?? '',
            'product_image' => $detail['product_image'] ?? ($detail['image'] ?? ''),
            'quantity' => (int) ($detail['quantity'] ?? 0),
            'unit_price' => (float) ($detail['unit_price'] ?? 0),
            'discount_percentage' => (float) ($detail['discount_percentage'] ?? 0),
            'discounted_unit_price' => (float) ($detail['discounted_unit_price'] ?? 0),
            'subtotal' => (float) ($detail['subtotal'] ?? 0),
        ];
    }

    public function deliveryToArray(?Delivery $delivery): ?array
    {
        if (!$delivery) return null;
        return [
            'delivery_id' => $delivery->getDeliveryId(),
            'sale_id' => $delivery->getSaleId(),
            'user_id' => $delivery->getUserId(),
            'shipping_date' => $delivery->getShippingDate()?->format('Y-m-d H:i:s'),
            'delivery_date' => $delivery->getDeliveryDate()?->format('Y-m-d H:i:s'),
            'status' => $delivery->getStatus(),
        ];
    }

    public function addressToArray(?Address $address): ?array
    {
        if (!$address) return null;
        return [
            'address_id' => $address->getAddressId(),
            'street' => $address->getStreet(),
            'number' => $address->getNumber(),
            'neighborhood' => $address->getNeighborhood(),
            'municipality' => $address->getMunicipality(),
            'state' => $address->getState(),
            'zip_code' => $address->getZipCode(),
            'reference' => $address->getReference(),
            'alias' => $address->getAlias(),
            'full_address' => $address->getFullAddress(),
        ];
    }
}
