<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\Services;

use App\Core\Security\GuestCartToken;
use App\Framework\Session\SessionManagerInterface;
use App\Modules\Customers\Domain\Cart;
use App\Modules\Customers\Domain\CartRepositoryInterface;

/**
 * Carrito persistente en base de datos.
 *
 * Mantiene la misma API pública que el carrito de sesión (addItem,
 * updateQuantity, removeItem, clear, getCount, getSubtotal) sin exponer sus
 * detalles: el cliente lógico se resuelve por customer_id si hay sesión iniciada
 * o por la cookie de invitado (GuestCartToken) en caso contrario.
 *
 * Los ítems se leen con datos vivos del producto (nombre, precio, imagen) en
 * cada lectura, por lo que addItem() solo necesita el product_id y la cantidad
 * para registrar el ítem en la base de datos.
 */
class CartService
{
    private const LEGACY_CART_KEY = 'cart';

    private bool $legacyMigrated = false;

    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private GuestCartToken $guestCartToken,
        private SessionManagerInterface $session,
    ) {}

    /**
     * @return array<int, array>
     */
    public function getItems(): array
    {
        $cart = $this->resolveCart(mustCreate: false);
        if ($cart === null) {
            return [];
        }

        return $this->cartRepository->getItems($cart->getCartId());
    }

    public function addItem(int $productId, int $quantity = 1): void
    {
        $cart = $this->resolveCart(mustCreate: true);
        if ($cart === null) {
            return;
        }

        $this->cartRepository->incrementItem($cart, $productId, $quantity);
    }

    public function updateQuantity(int $productId, int $quantity): void
    {
        $cart = $this->resolveCart(mustCreate: false);
        if ($cart === null) {
            return;
        }

        if ($quantity <= 0) {
            $this->cartRepository->removeItem($cart, $productId);
            return;
        }

        $this->cartRepository->setItemQuantity($cart, $productId, $quantity);
    }

    public function removeItem(int $productId): void
    {
        $cart = $this->resolveCart(mustCreate: false);
        if ($cart === null) {
            return;
        }

        $this->cartRepository->removeItem($cart, $productId);
    }

    public function clear(): void
    {
        $cart = $this->resolveCart(mustCreate: false);
        if ($cart === null) {
            return;
        }

        $this->cartRepository->clearItems($cart);
    }

    public function getCount(): int
    {
        return array_sum(array_column($this->getItems(), 'quantity'));
    }

    public function getSubtotal(): float
    {
        $total = 0.0;
        foreach ($this->getItems() as $item) {
            $total += $item['price'] * $item['quantity'];
        }

        return $total;
    }

    /**
     * Carrito vigente: por customer_id si hay sesión, o por guest_token
     * (creando la cookie solo cuando $mustCreate lo requiere, p. ej. al
     * agregar el primer ítem de un invitado).
     */
    private function resolveCart(bool $mustCreate): ?Cart
    {
        $this->migrateLegacyCart();

        $customerId = (int) $this->session->get('customer.id', 0);
        if ($customerId > 0) {
            $cart = $this->cartRepository->findForCustomer($customerId);
            if ($cart === null && $mustCreate) {
                $cart = $this->cartRepository->createForCustomer($customerId);
            }

            return $cart;
        }

        $token = $mustCreate
            ? $this->guestCartToken->get()
            : $this->guestCartToken->current();

        if ($token === null) {
            return null;
        }

        $cart = $this->cartRepository->findForGuest($token);
        if ($cart === null && $mustCreate) {
            $cart = $this->cartRepository->createForGuest($token);
        }

        return $cart;
    }

    /**
     * Migración one-time del carrito de $_SESSION al carrito persistente,
     * bajo la identidad de invitado (se fundirá con la cuenta en login).
     */
    private function migrateLegacyCart(): void
    {
        if ($this->legacyMigrated) {
            return;
        }
        $this->legacyMigrated = true;

        if (!$this->session->has(self::LEGACY_CART_KEY)) {
            return;
        }

        $legacy = $this->session->get(self::LEGACY_CART_KEY, []);
        if (!is_array($legacy) || $legacy === []) {
            $this->session->remove(self::LEGACY_CART_KEY);
            return;
        }

        $token = $this->guestCartToken->get();
        $cart = $this->cartRepository->findForGuest($token)
            ?? $this->cartRepository->createForGuest($token);

        foreach ($legacy as $item) {
            if (!is_array($item) || !isset($item['product_id'], $item['quantity'])) {
                continue;
            }

            $this->cartRepository->incrementItem(
                $cart,
                (int) $item['product_id'],
                max(1, (int) $item['quantity'])
            );
        }

        $this->session->remove(self::LEGACY_CART_KEY);
    }
}