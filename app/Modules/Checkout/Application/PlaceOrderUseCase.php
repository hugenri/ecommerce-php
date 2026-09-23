<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application;

use App\Modules\Checkout\Domain\Sale;
use App\Modules\Checkout\Domain\SaleDetail;
use App\Modules\Checkout\Domain\SalePriceCalculator;
use App\Modules\Checkout\Domain\Delivery;
use App\Modules\Checkout\Domain\SaleRepositoryInterface;
use App\Modules\Inventory\Application\UseCases\RegisterSaleMovementUseCase;
use App\Modules\Products\Domain\ProductRepositoryInterface;

class PlaceOrderUseCase
{
    private const SALE_PAYMENT_PAID = 'paid';

    public function __construct(
        private SaleRepositoryInterface $saleRepository,
        private ProductRepositoryInterface $productRepository,
        private RegisterSaleMovementUseCase $registerSaleMovement,
        private SalePriceCalculator $salePriceCalculator,
    ) {}

    public function execute(
        int $customerId,
        int $addressId,
        string $paymentMethod,
        array $cartItems,
        string $paymentStatus = 'paid',
    ): Sale {
        if (empty($cartItems)) {
            throw new \DomainException('El carrito está vacío.');
        }

        $items = [];
        $subtotal = 0.0;

        foreach ($cartItems as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                throw new \DomainException('Producto inválido en el carrito.');
            }

            $product = $this->productRepository->findById($productId);
            if (!$product || !$product->isActive()) {
                throw new \DomainException("Producto no encontrado o no disponible.");
            }

            if ($product->getStock() < $quantity) {
                throw new \DomainException(
                    "Stock insuficiente para {$product->getName()}. Disponible: {$product->getStock()}, solicitado: {$quantity}."
                );
            }

            $unitPrice = $product->getPrice() ?? 0.0;
            $discountPct = $product->getDiscount();
            $discountedUnitPrice = $this->salePriceCalculator->discountedUnitPrice($unitPrice, $discountPct);
            $lineSubtotal = $this->salePriceCalculator->lineAmount($unitPrice, $discountPct, $quantity);

            $items[] = [
                'productId' => $productId,
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'discountPct' => $discountPct,
                'discountedUnitPrice' => $discountedUnitPrice,
                'lineSubtotal' => $lineSubtotal,
            ];

            $subtotal += $lineSubtotal;
        }

        $totals = $this->salePriceCalculator->totals($subtotal);

        $saleCode = $this->generateSaleCode();
        $now = new \DateTimeImmutable();

        $sale = new Sale(
            saleId: null,
            saleCode: $saleCode,
            customerId: $customerId,
            addressId: $addressId,
            paymentMethod: $paymentMethod,
            paymentStatus: $paymentStatus,
            saleDate: $now,
            subtotal: $totals->getSubtotal(),
            tax: $totals->getTax(),
            total: $totals->getTotal(),
            status: 'pending',
        );

        $result = $this->saleRepository->transaction(function () use ($sale, $items, $paymentStatus) {
            $createdSale = $this->saleRepository->createSale($sale);

            foreach ($items as $item) {
                $detail = new SaleDetail(
                    detailId: null,
                    saleId: $createdSale->getSaleId(),
                    productId: $item['productId'],
                    quantity: $item['quantity'],
                    unitPrice: $item['unitPrice'],
                    discountPercentage: $item['discountPct'],
                    discountedUnitPrice: $item['discountedUnitPrice'],
                    subtotal: $item['lineSubtotal'],
                );
                $this->saleRepository->createDetail($detail);
            }

            if ($paymentStatus === self::SALE_PAYMENT_PAID) {
                $this->deductStockForSale($createdSale);
            }

            $delivery = new Delivery(
                deliveryId: null,
                saleId: $createdSale->getSaleId(),
                status: 'pending',
            );
            $this->saleRepository->createDelivery($delivery);

            return $createdSale;
        });

        return $result;
    }

    /**
     * Descuenta el stock de los productos que componen una venta materializada.
     *
     * Método público reutilizable desde el webhook para el caso de una venta
     * creada en estado pendiente que es pagada posteriormente: descuenta el
     * stock por primera y única vez, registrando el movimiento de salida por
     * cada producto dentro de la misma transacción en la que se materializa
     * el pago.
     *
     * Llama a RegisterSaleMovementUseCase::deductStock, que descuenta y registra
     * un movimiento auditado de forma atómica.
     */
    public function deductStockForSale(Sale $sale): void
    {
        $saleId = $sale->getSaleId();

        if ($saleId === null) {
            throw new \DomainException('No se puede descontar stock de una venta sin identificar.');
        }

        $details = $this->saleRepository->getDetailsForCancel($saleId);

        foreach ($details as $detail) {
            $productId = (int) ($detail['product_id'] ?? 0);
            $quantity = (int) ($detail['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $this->registerSaleMovement->deductStock(
                productId: $productId,
                quantity: $quantity,
                saleId: $saleId,
                reason: 'Venta ' . $sale->getSaleCode(),
            );
        }
    }

    private function generateSaleCode(): string
    {
        return 'OC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
}
