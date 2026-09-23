<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\UseCases;

use App\Modules\Customers\Application\Services\CartService;
use App\Modules\Products\Domain\ProductRepositoryInterface;

class AddToCartUseCase
{
    public function __construct(
        private CartService $cartService,
        private ProductRepositoryInterface $productRepository,
    ) {}

    /**
     * Valida el producto y la cantidad, y agrega el ítem al carrito. El
     * carrito se lee con datos vivos del producto en cada lectura.
     *
     * @throws \DomainException si el producto no está disponible, la cantidad
     *                          no es un entero >= 1 o supera el stock.
     */
    public function execute(int|string|null $productId, string|int|null $quantity = 1): void
    {
        $productId = $this->normalizeProductId($productId);
        $quantity = $this->normalizeQuantity($quantity);

        $product = $this->productRepository->findVisible($productId);
        if (!$product) {
            throw new \DomainException('El producto no está disponible.');
        }

        if ($product->getStock() < $quantity) {
            throw new \DomainException(
                sprintf(
                    'Stock insuficiente para %s. Disponible: %d, solicitado: %d.',
                    $product->getName(),
                    $product->getStock(),
                    $quantity
                )
            );
        }

        $this->cartService->addItem(
            productId: $product->getProductId(),
            quantity: $quantity,
        );
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

    private function normalizeQuantity(string|int|null $quantity): int
    {
        $raw = ($quantity === null || $quantity === '') ? 1 : $quantity;

        $validated = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validated === false) {
            throw new \DomainException('La cantidad debe ser un número entero mayor o igual a 1.');
        }

        return $validated;
    }
}
