<?php

declare(strict_types=1);

namespace App\Modules\Store\Presentation\Controllers;

use App\Core\Controller;
use App\Modules\Customers\Application\Services\CartService;
use App\Modules\Products\Domain\ProductRepositoryInterface;
use App\Modules\Categories\Domain\CategoryRepositoryInterface;
use App\Modules\Settings\Application\Services\SettingsService;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class HomeController extends Controller
{

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private SettingsService $settingsService,
        private CartService $cartService,
        SessionManagerInterface $sessionManager,
        Response $response
    ) {
        parent::__construct($sessionManager, $response);
    }

    public function home()
    {
        $categories = $this->categoryRepository->catalogCategories();

        $recentProducts = $this->productRepository->recentProducts(8);

        $saleProducts = $this->productRepository->saleProducts(8);

        $customer = $this->sessionManager->get('customer');
        $cartCount = $this->cartService->getCount();

        $siteSettings = $this->settingsService->getSiteSettings();
        $homeBanner = $this->settingsService->getHeroBanner();

        $this->view('home', [
            'categories' => $categories,
            'recentProducts' => $recentProducts,
            'saleProducts' => $saleProducts,
            'customer' => $customer,
            'cartCount' => $cartCount,
            'siteSettings' => $siteSettings,
            'homeBanner' => $homeBanner,
        ]);
    }
}
