<?php

declare(strict_types=1);

namespace App\Modules\Store\Presentation\Controllers;

use App\Core\Controller;
use App\Modules\Customers\Application\Services\CartService;
use App\Modules\Customers\Application\UseCases\AddToCartUseCase;
use App\Modules\Products\Domain\ProductRepositoryInterface;
use App\Http\Request;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class CartController extends Controller
{

    public function __construct(
        private AddToCartUseCase $addToCartUseCase,
        private ProductRepositoryInterface $productRepository,
        private CartService $cartService,
        private Request $request,
        SessionManagerInterface $sessionManager,
        Response $response
    ) {
        parent::__construct($sessionManager, $response);
    }

    public function showCart()
    {
        $payload = $this->cartPayload();

        if ($this->isAjax()) {
            $this->success($payload);
            return;
        }

        $this->view('cart', [
            'items' => $payload['items'],
            'subtotal' => $payload['subtotal'],
            'iva' => $payload['iva'],
            'total' => $payload['total'],
            'customer' => $this->sessionManager->get('customer'),
            'cartCount' => $payload['count'],
        ]);
    }

    public function addToCart()
    {
        $productId = $this->request->input('product_id');
        $quantity = $this->request->input('quantity');

        try {
            $this->addToCartUseCase->execute($productId, $quantity);
        } catch (\DomainException $e) {
            if ($this->isAjax()) {
                $this->error($e->getMessage());
                return;
            }
            $this->redirect('/shop');
            return;
        }

        if ($this->isAjax()) {
            $this->success($this->cartPayload());
            return;
        }

        $redirect = $this->request->input('redirect');
        if (!is_string($redirect) || !str_starts_with($redirect, '/')) {
            $redirect = '/shop';
        }
        $this->redirect($redirect);
    }

    public function updateCart()
    {
        $data = $this->request->post();
        $productId = (int) ($data['product_id'] ?? 0);
        $quantity = (int) ($data['quantity'] ?? 0);

        if ($productId > 0) {
            $this->cartService->updateQuantity($productId, $quantity);
        }

        $this->success($this->cartPayload());
    }

    public function removeFromCart(int $id)
    {
        $this->cartService->removeItem($id);

        $this->success($this->cartPayload());
    }

    public function clearCart()
    {
        $this->cartService->clear();

        $this->success($this->cartPayload());
    }

    /**
     * Estado actual del carrito, construido a partir de CartService y del
     * repositorio de productos. Única fuente de datos del carrito (lo usan
     * tanto la página /cart como el Mini Cart vía AJAX).
     *
     * Los productos que ya no cumplen la regla del catálogo público (inactivos
     * o sin movimientos de inventario) se descartan del carrito y se purgan de
     * la sesión para que el cliente solo vea productos comprables.
     */
    private function cartPayload(): array
    {
        $items = $this->cartService->getItems();
        $payload = [];

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $product = $this->productRepository->findVisible($productId);

            if (!$product) {
                $this->cartService->removeItem($productId);
                continue;
            }

            $payload[] = [
                'product_id' => $productId,
                'name' => $product->getName(),
                'price' => (float) $product->getPrice(),
                'image' => $product->getImage(),
                'quantity' => (int) $item['quantity'],
                'stock' => (int) $product->getStock(),
                'subtotal' => round((float) $product->getPrice() * (int) $item['quantity'], 2),
            ];
        }

        $subtotal = array_sum(array_column($payload, 'subtotal'));
        $count = array_sum(array_column($payload, 'quantity'));
        $iva = $subtotal * 0.16;

        return [
            'items' => $payload,
            'count' => $count,
            'subtotal' => round($subtotal, 2),
            'iva' => round($iva, 2),
            'total' => round($subtotal + $iva, 2),
        ];
    }

    private function isAjax(): bool
    {
        return $this->request->header('x-requested-with') === 'XMLHttpRequest';
    }
}
