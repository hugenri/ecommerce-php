<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\UseCases;

use App\Core\Security\GuestCartToken;
use App\Modules\Customers\Domain\Cart;
use App\Modules\Customers\Domain\CartRepositoryInterface;
use App\Modules\Products\Domain\Product;
use App\Modules\Products\Domain\ProductRepositoryInterface;

/**
 * Fusión del carrito de invitado (cookie) en el carrito del cliente al iniciar
 * sesión. Las cantidades se suman recortando al stock disponible; los
 * productos que ya no están activos o sin stock se descartan silenciosamente.
 */
class MergeGuestCartUseCase
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private ProductRepositoryInterface $productRepository,
        private GuestCartToken $guestCartToken,
    ) {}

    public function execute(int $customerId): void
    {
        $token = $this->guestCartToken->current();

        if ($token === null) {
            $this->guestCartToken->clear();
            return;
        }

        $guestCart = $this->cartRepository->findForGuest($token);
        if ($guestCart === null) {
            $this->guestCartToken->clear();
            return;
        }

        $guestItems = $this->cartRepository->getItems($guestCart->getCartId());
        if ($guestItems === []) {
            $this->cartRepository->deleteCart($guestCart->getCartId());
            $this->guestCartToken->clear();
            return;
        }

        $products = $this->productRepository->findByIds(
            array_map(
                fn(array $item): int => (int) $item['product_id'],
                $guestItems
            )
        );

        $this->cartRepository->transaction(function () use ($customerId, $guestCart, $guestItems, $products): void {
            $customerCart = $this->cartRepository->findForCustomer($customerId)
                ?? $this->cartRepository->createForCustomer($customerId);

            foreach ($guestItems as $item) {
                $productId = (int) $item['product_id'];
                $product = $products[$productId] ?? null;

                if (!$product instanceof Product || !$product->isActive() || $product->getStock() <= 0) {
                    continue;
                }

                $quantity = min((int) $item['quantity'], $product->getStock());
                $this->cartRepository->incrementItem($customerCart, $productId, $quantity);
            }

            $this->cartRepository->deleteCart($guestCart->getCartId());
        });

        $this->guestCartToken->clear();
    }
}