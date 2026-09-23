<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Modules\Categories\Domain\CategoryRepositoryInterface;
use App\Modules\Categories\Persistence\CategoryRepository;
use App\Modules\Checkout\Domain\AddressRepositoryInterface;
use App\Modules\Checkout\Domain\PaymentTransactionRepositoryInterface;
use App\Modules\Checkout\Domain\SaleRepositoryInterface;
use App\Modules\Checkout\Persistence\AddressRepository;
use App\Modules\Checkout\Persistence\PaymentTransactionRepository;
use App\Modules\Checkout\Persistence\SaleRepository;
use App\Modules\Customers\Domain\CartRepositoryInterface;
use App\Modules\Customers\Domain\CustomerRepositoryInterface;
use App\Modules\Customers\Domain\CustomerTokenRepositoryInterface;
use App\Modules\Customers\Persistence\CartRepository;
use App\Modules\Customers\Persistence\CustomerRepository;
use App\Modules\Customers\Persistence\CustomerTokenRepository;
use App\Modules\Dashboard\Domain\DashboardRepositoryInterface;
use App\Modules\Dashboard\Persistence\DashboardRepository;
use App\Modules\Deliveries\Domain\DeliveryRepositoryInterface;
use App\Modules\Deliveries\Persistence\DeliveryRepository;
use App\Modules\EmployeeDashboard\Domain\EmployeeDashboardRepositoryInterface;
use App\Modules\EmployeeDashboard\Persistence\EmployeeDashboardRepository;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Identity\Domain\UserTokenRepositoryInterface;
use App\Modules\Identity\Persistence\UserRepository;
use App\Modules\Identity\Persistence\UserTokenRepository;
use App\Modules\Inventory\Domain\InventoryRepositoryInterface;
use App\Modules\Inventory\Persistence\InventoryRepository;
use App\Modules\Products\Domain\ProductRepositoryInterface;
use App\Modules\Products\Persistence\ProductRepository;
use App\Modules\Reports\Domain\ReportRepositoryInterface;
use App\Modules\Reports\Persistence\ReportRepository;
use App\Modules\Settings\Domain\HomeBannerRepositoryInterface;
use App\Modules\Settings\Domain\SiteSettingsRepositoryInterface;
use App\Modules\Settings\Persistence\HomeBannerRepository;
use App\Modules\Settings\Persistence\SiteSettingsRepository;
use App\Modules\Subcategories\Domain\SubcategoryRepositoryInterface;
use App\Modules\Subcategories\Persistence\SubcategoryRepository;

/**
 * Registra todos los bindings de repositorios de los módulos.
 */
class RepositoryServiceProvider
{
    public static function register(Container $container): void
    {
        $container->bind(UserRepositoryInterface::class, UserRepository::class);
        $container->bind(UserTokenRepositoryInterface::class, UserTokenRepository::class);
        $container->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
        $container->bind(SubcategoryRepositoryInterface::class, SubcategoryRepository::class);
        $container->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $container->bind(CustomerRepositoryInterface::class, CustomerRepository::class);
        $container->bind(CartRepositoryInterface::class, CartRepository::class);
        $container->bind(CustomerTokenRepositoryInterface::class, CustomerTokenRepository::class);
        $container->bind(SaleRepositoryInterface::class, SaleRepository::class);
        $container->bind(AddressRepositoryInterface::class, AddressRepository::class);
        $container->bind(PaymentTransactionRepositoryInterface::class, PaymentTransactionRepository::class);
        $container->bind(DashboardRepositoryInterface::class, DashboardRepository::class);
        $container->bind(EmployeeDashboardRepositoryInterface::class, EmployeeDashboardRepository::class);
        $container->bind(DeliveryRepositoryInterface::class, DeliveryRepository::class);
        $container->bind(InventoryRepositoryInterface::class, InventoryRepository::class);
        $container->bind(ReportRepositoryInterface::class, ReportRepository::class);
        $container->bind(SiteSettingsRepositoryInterface::class, SiteSettingsRepository::class);
        $container->bind(HomeBannerRepositoryInterface::class, HomeBannerRepository::class);
    }
}