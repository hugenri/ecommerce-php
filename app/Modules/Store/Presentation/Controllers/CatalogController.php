<?php

declare(strict_types=1);

namespace App\Modules\Store\Presentation\Controllers;

use App\Core\Controller;
use App\Core\Validation\Validator;
use App\Modules\Customers\Application\Services\CartService;
use App\Modules\Products\Domain\Product;
use App\Modules\Products\Domain\ProductRepositoryInterface;
use App\Modules\Categories\Domain\CategoryRepositoryInterface;
use App\Modules\Subcategories\Domain\SubcategoryRepositoryInterface;
use App\Modules\Store\Application\UseCases\SearchSuggestionsUseCase;
use App\Http\Request;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class CatalogController extends Controller
{

    private const SEARCH_MIN_LENGTH = 2;

    private const SEARCH_MAX_LENGTH = 100;

    private const SUGGESTION_LIMIT = 8;

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private SubcategoryRepositoryInterface $subcategoryRepository,
        private CartService $cartService,
        private SearchSuggestionsUseCase $searchSuggestions,
        private Validator $validator,
        private Request $request,
        SessionManagerInterface $sessionManager,
        Response $response
    ) {
        parent::__construct($sessionManager, $response);
    }

    /**
     * Sugerencias de búsqueda en tiempo real (JSON).
     */
    public function suggestions()
    {
        $query = $this->normalizeTerm($this->request->get('q'));

        if ($query === '') {
            $this->success([]);
            return;
        }

        $errors = $this->validator->validate(
            ['q' => $query],
            [
                'q' => 'string|min:' . self::SEARCH_MIN_LENGTH . '|max:' . self::SEARCH_MAX_LENGTH,
            ]
        );

        if ($this->validator->hasErrors($errors)) {
            $this->validationError($errors);
            return;
        }

        $products = $this->searchSuggestions->execute($query, self::SUGGESTION_LIMIT);

        $data = array_map(fn(Product $product): array => [
            'product_id' => $product->getProductId(),
            'name' => $product->getName(),
            'image' => $product->getImage() ?? '',
        ], $products);

        $this->success($data);
    }

    private function normalizeTerm(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    public function catalog()
    {
        $page = max(1, (int) ($this->request->get('page') ?? 1));
        $perPage = 12;
        $search = trim($this->request->get('search') ?? '');
        if (mb_strlen($search) > self::SEARCH_MAX_LENGTH) {
            $search = mb_substr($search, 0, self::SEARCH_MAX_LENGTH);
        }
        $categoryId = $this->request->get('category_id');
        $subcategoryId = $this->request->get('subcategory_id');
        $sort = $this->request->get('sort', 'newest');

        $sortMap = [
            'newest' => ['products.created_at', 'DESC'],
            'price_asc' => ['products.price', 'ASC'],
            'price_desc' => ['products.price', 'DESC'],
            'name_asc' => ['products.name', 'ASC'],
        ];
        $sortBy = $sortMap[$sort][0] ?? 'products.created_at';
        $sortDir = $sortMap[$sort][1] ?? 'DESC';

        $filters = [];

        if ($categoryId) {
            $filters['category_id'] = (int) $categoryId;
        }
        if ($subcategoryId) {
            $filters['subcategory_id'] = (int) $subcategoryId;
        }

        $result = $this->productRepository->paginateCatalog(
            page: $page,
            perPage: $perPage,
            search: $search,
            sortBy: $sortBy,
            sortDir: $sortDir,
            filters: $filters
        );

        $categories = $this->categoryRepository->catalogCategories();

        $subcategories = $categoryId
            ? $this->subcategoryRepository->findByCategory((int) $categoryId)
            : [];

        $customer = $this->sessionManager->get('customer');
        $cartCount = $this->cartService->getCount();

        $this->view('catalog', [
            'products' => $result['data'],
            'meta' => $result['meta'],
            'categories' => $categories,
            'subcategories' => $subcategories,
            'currentCategory' => $categoryId,
            'currentSubcategory' => $subcategoryId,
            'currentSort' => $sort,
            'search' => $search,
            'searchQuery' => $search,
            'customer' => $customer,
            'cartCount' => $cartCount,
        ]);
    }

    public function product(int $id)
    {
        $product = $this->productRepository->findVisible($id);
        if (!$product) {
            $this->redirect('/shop');
            return;
        }

        $images = $this->productRepository->getImages($id);

        $subcategory = $this->subcategoryRepository->findById($product->getSubcategoryId());
        $categoryName = '';
        $subcategoryName = '';
        if ($subcategory) {
            $subcategoryName = $subcategory->getName();
            $category = $this->categoryRepository->findById($subcategory->getCategoryId());
            if ($category) {
                $categoryName = $category->getName();
            }
        }

        $customer = $this->sessionManager->get('customer');
        $cartCount = $this->cartService->getCount();

        $this->view('product', [
            'product' => $product,
            'images' => $images,
            'categoryName' => $categoryName,
            'subcategoryName' => $subcategoryName,
            'customer' => $customer,
            'cartCount' => $cartCount,
        ]);
    }
}
