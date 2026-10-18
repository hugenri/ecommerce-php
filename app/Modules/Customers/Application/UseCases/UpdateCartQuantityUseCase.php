<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\UseCases;

use App\Modules\Customers\Application\Services\CartService;
use App\Modules\Products\Domain\ProductRepositoryInterface;

/**
 * Fija la cantidad de un producto en el carrito validando que no supere el
 * stock real del producto. Una cantidad <= 0 elimina el ítem.
 *
 * La validación es autoritativa en el servidor y se ejecuta dentro de una
 * transacción para evitar que solicitudes simultáneas excedan el stock.
 */
class UpdateCartQuantityUseCase
{
    public function __construct(
        private CartService $cartService,
        private ProductRepositoryInterface $productRepository,
    ) {}

    /**
     * @throws \DomainException si el producto no está disponible, la cantidad no
     *                          es un entero >= 0 o supera el stock.
     */
    public function execute(int|string|null $productId, int|string|null $quantity): void
    {
        $productId = $this->normalizeProductId($productId);
        $quantity = $this->normalizeQuantity($quantity);

        if ($quantity <= 0) {
            $this->cartService->updateQuantity($productId, 0);

            return;
        }

        $this->cartService->transaction(function () use ($productId, $quantity): void {
            $product = $this->productRepository->findVisible($productId);
            if (!$product) {
                throw new \DomainException('El producto no está disponible.');
            }

            if ($quantity > $product->getStock()) {
                throw new \DomainException(
                    sprintf(
                        'El stock máximo de %s es %d.',
                        $product->getName(),
                        $product->getStock()
                    )
                );
            }

            $this->cartService->updateQuantity($product->getProductId(), $quantity);
        });
    }

    private function normalizeProductId(int|string|null $productId): int
    {
        if ($productId === null || $productId === '') {
            throw new \DomainException('Producto inválido.');
        }

        $validated = filter_var($productId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validated === false) {
            throw new \DomainException('Producto inválido.');
        }

        return $validated;
    }

    private function normalizeQuantity(int|string|null $quantity): int
    {
        $raw = ($quantity === null || $quantity === '') ? 0 : $quantity;

        $validated = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($validated === false) {
            throw new \DomainException('La cantidad debe ser un número entero mayor o igual a 0.');
        }

        return $validated;
    }
}
