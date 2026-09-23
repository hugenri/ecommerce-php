<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Presentation\Controllers;

use App\Core\Controller;
use App\Modules\Dashboard\Application\GetDashboardSummaryUseCase;
use App\Modules\Dashboard\Application\GetSalesLast7DaysUseCase;
use App\Modules\Dashboard\Application\GetTopSellingProductsUseCase;
use App\Modules\Dashboard\Application\GetSalesByCategoryUseCase;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class DashboardController extends Controller
{

    public function __construct(
        private GetDashboardSummaryUseCase $getDashboardSummary,
        private GetSalesLast7DaysUseCase $getSalesLast7Days,
        private GetTopSellingProductsUseCase $getTopSellingProducts,
        private GetSalesByCategoryUseCase $getSalesByCategory,
        SessionManagerInterface $sessionManager,
        Response $response
    ) {
        parent::__construct($sessionManager, $response);
    }

    public function index()
    {
        $user = $this->sessionManager->get('user');

        return $this->view('dashboard', [
            'userName' => $user['name'] ?? 'Admin',
            'userEmail' => $user['email'] ?? '',
            'userRole' => $user['role'] ?? 'admin',
            'summary' => $this->getDashboardSummary->execute(),
            'last7Days' => $this->getSalesLast7Days->execute(),
            'topProducts' => $this->getTopSellingProducts->execute(),
            'salesByCategory' => $this->getSalesByCategory->execute(),
        ]);
    }
}
