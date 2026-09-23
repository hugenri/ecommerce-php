<?php

declare(strict_types=1);

namespace App\Modules\Customers\Persistence;

use App\Core\Database\Database;
use App\Modules\Customers\Domain\Cart;
use App\Modules\Customers\Domain\CartRepositoryInterface;

class CartRepository implements CartRepositoryInterface
{
    public function __construct(
        private Database $db,
    ) {}

    public function findForCustomer(int $customerId): ?Cart
    {
        $row = $this->db->selectOne(
            'SELECT * FROM carts WHERE customer_id = :customer_id LIMIT 1',
            ['customer_id' => $customerId]
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findForGuest(string $guestToken): ?Cart
    {
        $row = $this->db->selectOne(
            'SELECT * FROM carts WHERE guest_token = :guest_token LIMIT 1',
            ['guest_token' => $guestToken]
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function createForCustomer(int $customerId): Cart
    {
        $cartId = $this->db->insert('carts', [
            'customer_id' => $customerId,
            'guest_token' => null,
            'status' => 'open',
        ]);

        return new Cart(
            cartId: (int) ($cartId ?? 0),
            customerId: $customerId,
            guestToken: null,
            status: 'open',
        );
    }

    public function createForGuest(string $guestToken): Cart
    {
        $cartId = $this->db->insert('carts', [
            'customer_id' => null,
            'guest_token' => $guestToken,
            'status' => 'open',
        ]);

        return new Cart(
            cartId: (int) ($cartId ?? 0),
            customerId: null,
            guestToken: $guestToken,
            status: 'open',
        );
    }

    public function incrementItem(Cart $cart, int $productId, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $sql = 'INSERT INTO cart_items (cart_id, product_id, quantity)
                VALUES (:cart_id, :product_id, :quantity)
                ON DUPLICATE KEY UPDATE quantity = quantity + :quantity_add';

        $this->db->query($sql, [
            'cart_id' => $cart->getCartId(),
            'product_id' => $productId,
            'quantity' => $quantity,
            'quantity_add' => $quantity,
        ]);
    }

    public function setItemQuantity(Cart $cart, int $productId, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeItem($cart, $productId);
            return;
        }

        $sql = 'INSERT INTO cart_items (cart_id, product_id, quantity)
                VALUES (:cart_id, :product_id, :quantity)
                ON DUPLICATE KEY UPDATE quantity = :quantity_update';

        $this->db->query($sql, [
            'cart_id' => $cart->getCartId(),
            'product_id' => $productId,
            'quantity' => $quantity,
            'quantity_update' => $quantity,
        ]);
    }

    public function removeItem(Cart $cart, int $productId): void
    {
        $this->db->delete('cart_items', [
            'cart_id' => $cart->getCartId(),
            'product_id' => $productId,
        ]);
    }

    public function clearItems(Cart $cart): void
    {
        $this->db->delete('cart_items', ['cart_id' => $cart->getCartId()]);
    }

    public function deleteCart(int $cartId): void
    {
        $this->db->delete('carts', ['cart_id' => $cartId]);
    }

    public function getItems(int $cartId): array
    {
        $rows = $this->db->select(
            'SELECT ci.product_id, ci.quantity, p.name, p.price, p.discount, p.image
             FROM cart_items ci
             INNER JOIN products p ON p.product_id = ci.product_id
             WHERE ci.cart_id = :cart_id
             ORDER BY ci.cart_item_id ASC',
            ['cart_id' => $cartId]
        );

        $items = [];
        foreach ($rows as $row) {
            $items[(string) $row['product_id']] = [
                'product_id' => (int) $row['product_id'],
                'name' => (string) $row['name'],
                'price' => isset($row['price']) ? (float) $row['price'] : 0.0,
                'discount' => isset($row['discount']) ? (float) $row['discount'] : 0.0,
                'image' => $row['image'] ?? null,
                'quantity' => (int) $row['quantity'],
            ];
        }

        return $items;
    }

    public function transaction(callable $callback): mixed
    {
        return $this->db->transaction($callback);
    }

    public function purgeExpiredGuestCarts(int $days): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - $days * 86400);

        $sql = 'DELETE FROM carts
                WHERE guest_token IS NOT NULL
                  AND status = :status
                  AND updated_at < :cutoff';
        $stmt = $this->db->query($sql, [
            'status' => 'open',
            'cutoff' => $cutoff,
        ]);

        return $stmt->rowCount();
    }

    private function hydrate(array $row): Cart
    {
        return new Cart(
            cartId: (int) $row['cart_id'],
            customerId: isset($row['customer_id']) ? (int) $row['customer_id'] : null,
            guestToken: $row['guest_token'] ?? null,
            status: $row['status'] ?? 'open',
        );
    }
}