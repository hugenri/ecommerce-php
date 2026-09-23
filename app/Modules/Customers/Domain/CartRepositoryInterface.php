<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

interface CartRepositoryInterface
{
    public function findForCustomer(int $customerId): ?Cart;

    public function findForGuest(string $guestToken): ?Cart;

    public function createForCustomer(int $customerId): Cart;

    public function createForGuest(string $guestToken): Cart;

    /**
     * Suma la cantidad indicada al ítem del carrito o lo inserta si no existe.
     */
    public function incrementItem(Cart $cart, int $productId, int $quantity): void;

    /**
     * Fija la cantidad exacta del ítem (cantidad <= 0 lo elimina).
     */
    public function setItemQuantity(Cart $cart, int $productId, int $quantity): void;

    public function removeItem(Cart $cart, int $productId): void;

    public function clearItems(Cart $cart): void;

    public function deleteCart(int $cartId): void;

    /**
     * Ítems del carrito con datos vivos del producto, indexados por
     * product_id (string), en el mismo formato que consumía el carrito
     * de sesión:
     *
     * array{
     *   product_id: int,
     *   name: string,
     *   price: float,
     *   image: string|null,
     *   quantity: int
     * }
     *
     * @return array<int, array>
     */
    public function getItems(int $cartId): array;

    public function transaction(callable $callback): mixed;

    /**
     * Elimina los carritos de invitado inactivos más antiguos que $days días
     * (junto con sus ítems, vía FK CASCADE). Devuelve el número de carritos
     * eliminados.
     */
    public function purgeExpiredGuestCarts(int $days): int;
}