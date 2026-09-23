<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\UseCases;

use App\Config\ConektaConfig;
use App\Config\PaypalConfig;
use App\Modules\Checkout\Application\Services\PaymentGatewayResolver;
use App\Modules\Checkout\Domain\AddressRepositoryInterface;
use App\Modules\Checkout\Domain\BuyerInfo;
use App\Modules\Checkout\Domain\PaymentCheckout;
use App\Modules\Checkout\Domain\PaymentGatewayInterface;
use App\Modules\Checkout\Domain\PaymentOrder;
use App\Modules\Checkout\Domain\PaymentOrderItem;
use App\Modules\Checkout\Domain\SalePriceCalculator;
use App\Modules\Products\Domain\ProductRepositoryInterface;

/**
 * Crea la orden técnica de pago (Conekta o PayPal) con checkout hospedado.
 *
 * NO persiste nada en la base de datos: solo valida stock/precios,
 * construye la orden de pago y retorna el checkout con la URL de
 * redirección. La metadata del cliente, dirección y artículos se
 * almacena en la orden de pago para que el webhook (Conekta) la recupere
 * al materializar la venta.
 *
 * Los artículos se reciben explícitamente (desde el carrito en /checkout
 * o desde los detalles de una venta ya persistida en /pago), por lo que
 * este caso de uso no depende del carrito.
 *
 * Es genérico respecto al proveedor: resuelve la pasarela y la moneda según
 * el parámetro $providerId. Se conserva el nombre de la clase por
 * compatibilidad con el flujo Conekta.
 */
class CreateConektaOrderUseCase
{
    private const DEFAULT_PROVIDER = 'conekta';

    public function __construct(
        private PaymentGatewayResolver $gatewayResolver,
        private AddressRepositoryInterface $addressRepository,
        private ProductRepositoryInterface $productRepository,
        private ConektaConfig $conektaConfig,
        private PaypalConfig $paypalConfig,
        private SalePriceCalculator $salePriceCalculator,
    ) {}

    /**
     * @param int                       $customerId
     * @param int                       $addressId
     * @param ?BuyerInfo                $buyer
     * @param array<int, array<string, mixed>> $cartItems Artículos {product_id, quantity}
     * @param string                    $providerId 'conekta' (default) o 'paypal'
     */
    public function execute(
        int $customerId,
        int $addressId,
        ?BuyerInfo $buyer = null,
        array $cartItems = [],
        string $providerId = self::DEFAULT_PROVIDER,
    ): PaymentCheckout {
        if (empty($cartItems)) {
            throw new \DomainException('El carrito está vacío.');
        }

        if (!$this->validateAddress($customerId, $addressId)) {
            throw new \DomainException('Dirección de envío inválida.');
        }

        $paymentOrder = $this->buildPaymentOrder($cartItems, $customerId, $addressId, $buyer, $providerId);

        return $this->gateway($providerId)->createRedirectCheckout($paymentOrder);
    }

    /**
     * @param array<int, array<string, mixed>> $cartItems Artículos {product_id, quantity}
     */
    private function buildPaymentOrder(
        array $cartItems,
        int $customerId,
        int $addressId,
        ?BuyerInfo $buyer,
        string $providerId,
    ): PaymentOrder
    {
        $subtotal = 0.0;
        $paymentItems = [];
        $metadataItems = [];

        foreach ($cartItems as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                throw new \DomainException('Producto inválido en el carrito.');
            }

            $product = $this->productRepository->findById($productId);

            if (!$product || !$product->isActive()) {
                throw new \DomainException('Producto no encontrado o no disponible.');
            }

            if ($product->getStock() < $quantity) {
                throw new \DomainException(
                    "Stock insuficiente para {$product->getName()}. Disponible: {$product->getStock()}, solicitado: {$quantity}."
                );
            }

            $unitPrice = $product->getPrice() ?? 0.0;
            $discountPct = $product->getDiscount();

            $subtotal += $this->salePriceCalculator->lineAmount($unitPrice, $discountPct, $quantity);

            $paymentItems[] = new PaymentOrderItem(
                name: $product->getName(),
                quantity: $quantity,
                unitAmount: $this->salePriceCalculator->discountedUnitPrice($unitPrice, $discountPct),
            );

            $metadataItems[] = [
                'id' => $productId,
                'qty' => $quantity,
            ];
        }

        $totals = $this->salePriceCalculator->totals($subtotal);

        return new PaymentOrder(
            amount: $totals->getTotal(),
            currency: $this->currency($providerId),
            description: 'Pedido ecommerce',
            reference: (string) $customerId,
            items: $paymentItems,
            subtotal: $totals->getSubtotal(),
            tax: $totals->getTax(),
            buyer: $buyer,
            metadata: [
                'customer_id' => $customerId,
                'address_id' => $addressId,
                'items' => $metadataItems,
            ],
        );
    }

    private function currency(string $providerId): string
    {
        return $providerId === 'paypal'
            ? $this->paypalConfig->currency()
            : $this->conektaConfig->currency();
    }

    private function validateAddress(int $customerId, int $addressId): bool
    {
        $address = $this->addressRepository->findById($addressId);

        return $address !== null && $address->getCustomerId() === $customerId;
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