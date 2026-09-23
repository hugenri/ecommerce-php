<?php

declare(strict_types=1);

namespace App\Modules\Products\Presentation\Controllers;

use App\Core\Controller;
use App\Modules\Products\Application\UseCases\CreateProductUseCase;
use App\Modules\Products\Application\UseCases\UpdateProductUseCase;
use App\Modules\Products\Application\UseCases\GetProductUseCase;
use App\Modules\Products\Application\UseCases\ListProductsUseCase;
use App\Modules\Products\Application\UseCases\SearchProductsUseCase;
use App\Modules\Products\Application\UseCases\ActivateProductUseCase;
use App\Modules\Products\Application\UseCases\DeactivateProductUseCase;
use App\Modules\Products\Application\UseCases\DeleteProductUseCase;
use App\Modules\Products\Application\UseCases\ChangeMainImageUseCase;
use App\Modules\Products\Application\UseCases\AddProductImageUseCase;
use App\Modules\Products\Application\UseCases\RemoveProductImageUseCase;
use App\Modules\Products\Application\UseCases\ReorderProductImagesUseCase;
use App\Modules\Products\Application\UseCases\UpdateProductPriceUseCase;
use App\Modules\Products\Application\UseCases\UpdateProductDiscountUseCase;
use App\Modules\Products\Presentation\ProductSerializer;
use App\Modules\Products\Domain\ProductRepositoryInterface;
use App\Http\Request;
use App\Core\Validation\Validator;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class ProductController extends Controller
{

    public function __construct(
        private ListProductsUseCase $listProducts,
        private SearchProductsUseCase $searchProducts,
        private CreateProductUseCase $createProduct,
        private GetProductUseCase $getProduct,
        private UpdateProductUseCase $updateProduct,
        private DeleteProductUseCase $deleteProduct,
        private ActivateProductUseCase $activateProduct,
        private DeactivateProductUseCase $deactivateProduct,
        private ChangeMainImageUseCase $changeMainImage,
        private AddProductImageUseCase $addProductImage,
        private RemoveProductImageUseCase $removeProductImage,
        private ReorderProductImagesUseCase $reorderProductImages,
        private UpdateProductPriceUseCase $updateProductPrice,
        private UpdateProductDiscountUseCase $updateProductDiscount,
        private ProductRepositoryInterface $productRepository,
        private ProductSerializer $serializer,
        private Validator $validator,
        private Request $request,
        SessionManagerInterface $sessionManager,
        Response $response
    ) {
        parent::__construct($sessionManager, $response);
    }

    public function index()
    {
        $user = $this->sessionManager->get('user');
        return $this->view('index', [
            'userName' => $user['name'] ?? '',
            'userEmail' => $user['email'] ?? '',
        ]);
    }

    public function data()
    {
        $page = max(1, (int) ($this->request->get('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->get('per_page') ?? 10)));
        $filters = array_filter([
            'search' => $this->request->get('search'),
            'subcategory_id' => $this->request->get('subcategory_id') !== null ? (int) $this->request->get('subcategory_id') : null,
            'category_id' => $this->request->get('category_id') !== null ? (int) $this->request->get('category_id') : null,
            'status' => $this->request->get('status'),
            'sort_by' => $this->request->get('sort_by') ?? 'products.name',
            'sort_order' => strtoupper($this->request->get('sort_order') ?? 'ASC'),
        ], fn($v) => $v !== null && $v !== '');

        $result = $this->listProducts->execute(
            $page,
            $perPage,
            $filters['search'] ?? '',
            $filters['sort_by'] ?? 'products.name',
            $filters['sort_order'] ?? 'ASC',
            $filters
        );

        return $this->success(
            array_map(fn($p) => $this->serializer->toArray($p), $result['data']),
            'Lista de productos',
            200,
            $result['meta']
        );
    }

    public function show(int $id)
    {
        $product = $this->getProduct->execute($id);
        if (!$product) return $this->notFound('Producto no encontrado.');

        $data = $this->serializer->toArray($product);
        $data['images'] = array_map(
            fn($img) => $this->serializer->imageToArray($img),
            $this->productRepository->getImages($id)
        );

        return $this->success($data);
    }

    public function store()
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'subcategory_id' => 'required|numeric',
            'product_code' => 'required|string|min:5|max:40',
            'name' => 'required|string|min:10|max:120',
            'description' => 'nullable|string|min:10|max:120',
            'image' => 'required|string|max:150|url',

        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        try {
            $product = $this->createProduct->execute($data);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        }

        return $this->success(
            ['product' => $this->serializer->toArray($product)],
            'Producto creado exitosamente.',
            201
        );
    }

    public function update(int $id)
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'subcategory_id' => 'sometimes|numeric',
            'name' => 'sometimes|string|min:10|max:120',
            'description' => 'nullable|string|min:10|max:120',
            'image' => 'nullable|string|max:150|url',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        try {
            $product = $this->updateProduct->execute($id, $data);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        }

        if (!$product) return $this->notFound('Producto no encontrado.');

        return $this->success(
            ['product' => $this->serializer->toArray($product)],
            'Producto actualizado exitosamente.'
        );
    }

    public function destroy(int $id)
    {
        try {
            $deleted = $this->deleteProduct->execute($id);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        }

        if (!$deleted) return $this->notFound('Producto no encontrado.');

        return $this->success(null, 'Producto eliminado exitosamente.');
    }

    public function search()
    {
        $query = trim($this->request->get('q') ?? '');
        if (strlen($query) < 2) {
            return $this->success([], 'Búsqueda de productos');
        }

        $results = $this->searchProducts->execute($query);
        return $this->success(
            array_map(fn($p) => $this->serializer->toArray($p), $results)
        );
    }

    public function activate(int $id)
    {
        $product = $this->activateProduct->execute($id);
        if (!$product) return $this->notFound('Producto no encontrado.');
        return $this->success(
            ['product' => $this->serializer->toArray($product)],
            'Producto activado exitosamente.'
        );
    }

    public function deactivate(int $id)
    {
        $product = $this->deactivateProduct->execute($id);
        if (!$product) return $this->notFound('Producto no encontrado.');
        return $this->success(
            ['product' => $this->serializer->toArray($product)],
            'Producto desactivado exitosamente.'
        );
    }

    public function changeImage(int $id)
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'image' => 'required|string|max:150',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        $product = $this->changeMainImage->execute($id, $data['image']);
        if (!$product) return $this->notFound('Producto no encontrado.');

        return $this->success(
            ['product' => $this->serializer->toArray($product)],
            'Imagen principal actualizada exitosamente.'
        );
    }

    public function addImage(int $id)
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'image' => 'required|string|max:150',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        try {
            $image = $this->addProductImage->execute($id, $data['image']);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success(
            ['image' => $this->serializer->imageToArray($image)],
            'Imagen agregada exitosamente.',
            201
        );
    }

    public function removeImage(int $id, int $imageId)
    {
        $deleted = $this->removeProductImage->execute($imageId);
        if (!$deleted) return $this->notFound('Imagen no encontrada.');

        return $this->success(null, 'Imagen eliminada exitosamente.');
    }

    public function reorderImages(int $id)
    {
        $data = $this->request->post();
        $imageIds = $data['image_ids'] ?? [];

        if (!is_array($imageIds) || empty($imageIds)) {
            return $this->error('Debe proporcionar un arreglo de image_ids.', 400);
        }

        $this->reorderProductImages->execute($id, $imageIds);
        return $this->success(null, 'Imágenes reordenadas exitosamente.');
    }

    public function updatePrice(int $id)
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'price' => 'required|decimal',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        try {
            $product = $this->updateProductPrice->execute($id, (float) $data['price']);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 400);
        }

        if (!$product) return $this->notFound('Producto no encontrado.');

        return $this->success(
            ['product' => $this->serializer->toArray($product)],
            'Precio actualizado exitosamente.'
        );
    }

    public function updateDiscount(int $id)
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'discount' => 'required|decimal',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        try {
            $product = $this->updateProductDiscount->execute($id, (float) $data['discount']);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 400);
        }

        if (!$product) return $this->notFound('Producto no encontrado.');

        return $this->success(
            ['product' => $this->serializer->toArray($product)],
            'Descuento actualizado exitosamente.'
        );
    }
}
