<?php

use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\CustomerAuthMiddleware;
use App\Middleware\EmployeeMiddleware;
use App\Modules\Dashboard\Presentation\Controllers\DashboardController;
use App\Modules\Identity\Presentation\Controllers\AuthController;
use App\Modules\Identity\Presentation\Controllers\UserController;
use App\Modules\Categories\Presentation\Controllers\CategoryController;
use App\Modules\Subcategories\Presentation\Controllers\SubcategoryController;
use App\Modules\Products\Presentation\Controllers\ProductController;
use App\Modules\Customers\Presentation\Controllers\CustomerController;
use App\Modules\Customers\Presentation\Controllers\CustomerAuthController;
use App\Modules\Customers\Presentation\Controllers\AccountController;
use App\Modules\Store\Presentation\Controllers\HomeController;
use App\Modules\Store\Presentation\Controllers\CatalogController;
use App\Modules\Store\Presentation\Controllers\CartController;
use App\Modules\Store\Presentation\Controllers\CustomerPasswordResetController;
use App\Modules\Identity\Presentation\Controllers\AdminPasswordResetController;
use App\Modules\Checkout\Presentation\Controllers\CheckoutController;
use App\Modules\Checkout\Presentation\Controllers\SalesController;
use App\Modules\Checkout\Presentation\Controllers\ConektaWebhookController;
use App\Modules\Deliveries\Presentation\Controllers\DeliveriesController;
use App\Modules\EmployeeDashboard\Presentation\Controllers\EmployeeDashboardController;
use App\Modules\Identity\Presentation\Controllers\ProfileController;
use App\Modules\Inventory\Presentation\Controllers\InventoryController;
use App\Modules\Reports\Presentation\Controllers\ReportsController;
use App\Modules\Settings\Presentation\Controllers\SettingsController;

// ─── Front-office: Store ──────────────────────────────

$router->get('/', [HomeController::class, 'home']);

$router->get('/reset-password', [CustomerPasswordResetController::class, 'show']);
$router->post('/reset-password', [CustomerPasswordResetController::class, 'submit']);

$router->get('/shop', [CatalogController::class, 'catalog']);
$router->get('/product/{id}', [CatalogController::class, 'product']);
$router->get('/search/suggestions', [CatalogController::class, 'suggestions']);

$router->get('/cart', [CartController::class, 'showCart']);
$router->post('/cart/add', [CartController::class, 'addToCart']);
$router->post('/cart/update', [CartController::class, 'updateCart']);
$router->post('/cart/remove/{id}', [CartController::class, 'removeFromCart'], [CsrfMiddleware::class]);
$router->post('/cart/clear', [CartController::class, 'clearCart'], [CsrfMiddleware::class]);

// ─── Front-office: Customer Auth ──────────────────────

$router->get('/login', [CustomerAuthController::class, 'showLogin']);
$router->post('/login', [CustomerAuthController::class, 'login']);
$router->get('/register', [CustomerAuthController::class, 'showRegister']);
$router->post('/register', [CustomerAuthController::class, 'register']);
$router->post('/logout', [CustomerAuthController::class, 'logout'], [CsrfMiddleware::class]);

$router->get('/customer/verify-email', [CustomerAuthController::class, 'verifyEmail']);
$router->post('/customer/resend-verification', [CustomerAuthController::class, 'resendVerification']);

// ─── Front-office: Customer Account ───────────────────

$router->get('/account', [AccountController::class, 'account'], [CustomerAuthMiddleware::class]);
$router->post('/account/profile', [AccountController::class, 'updateProfile'], [CustomerAuthMiddleware::class]);
$router->post('/account/profile/name', [AccountController::class, 'updateNameAjax'], [CustomerAuthMiddleware::class]);
$router->post('/account/profile/phone', [AccountController::class, 'updatePhoneAjax'], [CustomerAuthMiddleware::class]);
$router->post('/account/password', [AccountController::class, 'changePassword'], [CustomerAuthMiddleware::class]);
$router->post('/account/address/new', [AccountController::class, 'addAddressFromAccount'], [CustomerAuthMiddleware::class]);
$router->get('/account/address/edit/{id}', [AccountController::class, 'getEditAddressForm'], [CustomerAuthMiddleware::class]);
$router->post('/account/address/edit/{id}', [AccountController::class, 'updateAddress'], [CustomerAuthMiddleware::class]);
$router->post('/account/address/delete/{id}', [AccountController::class, 'deleteAddress'], [CustomerAuthMiddleware::class]);
$router->post('/account/address/default/{id}', [AccountController::class, 'setDefaultAddress'], [CustomerAuthMiddleware::class]);

// ─── Checkout ─────────────────────────────────────────

$router->get('/checkout', [CheckoutController::class, 'checkout']);
$router->post('/checkout/address/new', [CheckoutController::class, 'addAddress'], [CustomerAuthMiddleware::class, CsrfMiddleware::class]);
$router->get('/checkout/confirmation/{id}', [CheckoutController::class, 'confirmation']);

$router->post('/checkout/paypal/place-order', [CheckoutController::class, 'placePaypalOrder'], [CustomerAuthMiddleware::class, CsrfMiddleware::class]);

// Flujo separado Checkout/Pago: en /checkout la venta se persiste como pending
// ("Pagar pedido") y se paga en /pago/{sale_code} leyendo de la venta persistida
// (no del carrito). Las rutas antiguas /checkout/*/create-order y
// /checkout/paypal/capture-order fueron eliminadas en la limpieza; el webhook y
// ConfirmPendingSalePaymentUseCase re-consultan la pasarela por provider_order_id,
// por lo que no dependen de ellas.
$router->post('/checkout/conekta/place-order', [CheckoutController::class, 'placeConektaOrder'], [CustomerAuthMiddleware::class, CsrfMiddleware::class]);

$router->get('/pago/{sale_code}', [CheckoutController::class, 'pago'], [CustomerAuthMiddleware::class]);
$router->post('/pago/{sale_code}/create-order', [CheckoutController::class, 'pagoCreateConektaOrder'], [CustomerAuthMiddleware::class, CsrfMiddleware::class]);
$router->post('/pago/{sale_code}/confirm', [CheckoutController::class, 'pagoConfirmConektaPayment'], [CustomerAuthMiddleware::class, CsrfMiddleware::class]);

$router->post('/webhooks/conekta', [ConektaWebhookController::class, 'handle']);

// ─── Access login ─────────────────────────────────────

$router->get('/access/login', [AuthController::class, 'index']);
$router->post('/access/login', [AuthController::class, 'login']);

$router->get('/access/reset-password', [AdminPasswordResetController::class, 'show']);
$router->post('/access/reset-password', [AdminPasswordResetController::class, 'submit']);

$router->get('/admin', [DashboardController::class, 'index'], [AuthMiddleware::class, AdminMiddleware::class]);

$router->group(['middleware' => [AuthMiddleware::class, CsrfMiddleware::class]], function ($router) {
    $router->get('/profile', [ProfileController::class, 'index']);
    $router->post('/profile', [ProfileController::class, 'update']);
    $router->post('/profile/password', [ProfileController::class, 'changePassword']);
});

$router->group(['middleware' => [AuthMiddleware::class, AdminMiddleware::class, CsrfMiddleware::class]], function ($router) {
    $router->get('/users', [UserController::class, 'index']);
    $router->get('/users/data', [UserController::class, 'data']);
    $router->get('/users/{id}', [UserController::class, 'show']);
    $router->post('/users', [UserController::class, 'store']);
    $router->put('/users/{id}', [UserController::class, 'update']);
    $router->delete('/users/{id}', [UserController::class, 'destroy']);
    $router->patch('/users/{id}', [UserController::class, 'toggleActive']);
    $router->post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
});

$router->group(['middleware' => [AuthMiddleware::class, AdminMiddleware::class, CsrfMiddleware::class]], function ($router) {
    $router->get('/categories', [CategoryController::class, 'index']);
    $router->get('/categories/data', [CategoryController::class, 'data']);
    $router->get('/categories/{id}', [CategoryController::class, 'show']);
    $router->post('/categories', [CategoryController::class, 'store']);
    $router->put('/categories/{id}', [CategoryController::class, 'update']);
    $router->delete('/categories/{id}', [CategoryController::class, 'destroy']);
    $router->patch('/categories/{id}/activate', [CategoryController::class, 'activate']);
    $router->patch('/categories/{id}/deactivate', [CategoryController::class, 'deactivate']);
});

$router->group(['middleware' => [AuthMiddleware::class, AdminMiddleware::class, CsrfMiddleware::class]], function ($router) {
    $router->get('/subcategories', [SubcategoryController::class, 'index']);
    $router->get('/subcategories/data', [SubcategoryController::class, 'data']);
    $router->get('/subcategories/by-category/{categoryId}', [SubcategoryController::class, 'byCategory']);
    $router->get('/subcategories/{id}', [SubcategoryController::class, 'show']);
    $router->post('/subcategories', [SubcategoryController::class, 'store']);
    $router->put('/subcategories/{id}', [SubcategoryController::class, 'update']);
    $router->delete('/subcategories/{id}', [SubcategoryController::class, 'destroy']);
    $router->patch('/subcategories/{id}/activate', [SubcategoryController::class, 'activate']);
    $router->patch('/subcategories/{id}/deactivate', [SubcategoryController::class, 'deactivate']);
});

$router->group(['middleware' => [AuthMiddleware::class, AdminMiddleware::class, CsrfMiddleware::class]], function ($router) {
    $router->get('/products', [ProductController::class, 'index']);
    $router->get('/products/data', [ProductController::class, 'data']);
    $router->get('/products/search', [ProductController::class, 'search']);
    $router->get('/products/{id}', [ProductController::class, 'show']);
    $router->post('/products', [ProductController::class, 'store']);
    $router->put('/products/{id}', [ProductController::class, 'update']);
    $router->delete('/products/{id}', [ProductController::class, 'destroy']);
    $router->patch('/products/{id}/activate', [ProductController::class, 'activate']);
    $router->patch('/products/{id}/deactivate', [ProductController::class, 'deactivate']);
    $router->post('/products/{id}/image', [ProductController::class, 'changeImage']);
    $router->post('/products/{id}/images', [ProductController::class, 'addImage']);
    $router->delete('/products/{id}/images/{imageId}', [ProductController::class, 'removeImage']);
    $router->put('/products/{id}/images/reorder', [ProductController::class, 'reorderImages']);
    $router->post('/products/{id}/price', [ProductController::class, 'updatePrice']);
    $router->post('/products/{id}/discount', [ProductController::class, 'updateDiscount']);
});

$router->group(['middleware' => [AuthMiddleware::class, AdminMiddleware::class, CsrfMiddleware::class]], function ($router) {
    $router->get('/customers', [CustomerController::class, 'index']);
    $router->get('/customers/data', [CustomerController::class, 'data']);
    $router->get('/customers/search', [CustomerController::class, 'search']);
    $router->get('/customers/{id}', [CustomerController::class, 'show']);
    $router->patch('/customers/{id}/activate', [CustomerController::class, 'activate']);
    $router->patch('/customers/{id}/deactivate', [CustomerController::class, 'deactivate']);
});

$router->group(['middleware' => [AuthMiddleware::class, AdminMiddleware::class, CsrfMiddleware::class]], function ($router) {
    $router->get('/sales', [SalesController::class, 'index']);
    $router->get('/sales/data', [SalesController::class, 'data']);
    $router->get('/sales/{id}', [SalesController::class, 'show']);
    $router->patch('/sales/{id}/status', [SalesController::class, 'updateStatus']);
    $router->patch('/sales/{id}/cancel', [SalesController::class, 'cancel']);
});

$router->group(['middleware' => [AuthMiddleware::class, AdminMiddleware::class, CsrfMiddleware::class]], function ($router) {
    $router->get('/inventory', [InventoryController::class, 'index']);
    $router->get('/inventory/data', [InventoryController::class, 'data']);
    $router->get('/inventory/movements', [InventoryController::class, 'movements']);
    $router->get('/inventory/low-stock', [InventoryController::class, 'lowStock']);
    $router->post('/inventory/add-stock', [InventoryController::class, 'store']);
    $router->post('/inventory/adjust', [InventoryController::class, 'adjust']);
});

$router->group(['middleware' => [AuthMiddleware::class, AdminMiddleware::class, CsrfMiddleware::class]], function ($router) {
    $router->get('/deliveries', [DeliveriesController::class, 'index']);
    $router->get('/deliveries/data', [DeliveriesController::class, 'data']);
    $router->get('/deliveries/pending', [DeliveriesController::class, 'pending']);
    $router->get('/deliveries/search', [DeliveriesController::class, 'search']);
    $router->get('/deliveries/employees', [DeliveriesController::class, 'employees']);
    $router->get('/deliveries/employee/{userId}', [DeliveriesController::class, 'employee']);
    $router->get('/deliveries/{id}', [DeliveriesController::class, 'show']);
    $router->post('/deliveries/{id}/assign', [DeliveriesController::class, 'assign']);
    $router->patch('/deliveries/{id}/status', [DeliveriesController::class, 'updateStatus']);
    $router->post('/deliveries/{id}/shipping-date', [DeliveriesController::class, 'updateShippingDate']);
    $router->post('/deliveries/{id}/delivery-date', [DeliveriesController::class, 'updateDeliveryDate']);
});

$router->group(['middleware' => [AuthMiddleware::class, AdminMiddleware::class]], function ($router) {
    $router->get('/reports', [ReportsController::class, 'index']);
    $router->get('/reports/sales', [ReportsController::class, 'sales']);
    $router->get('/reports/top-products', [ReportsController::class, 'topProducts']);
    $router->get('/reports/low-stock', [ReportsController::class, 'lowStock']);
    $router->get('/reports/inventory', [ReportsController::class, 'inventory']);
    $router->get('/reports/deliveries', [ReportsController::class, 'deliveries']);
});

$router->group(['middleware' => [AuthMiddleware::class, AdminMiddleware::class, CsrfMiddleware::class]], function ($router) {
    $router->get('/admin/settings/general', [SettingsController::class, 'general']);
    $router->post('/admin/settings/general', [SettingsController::class, 'saveGeneral']);
    $router->get('/admin/settings/home', [SettingsController::class, 'home']);
    $router->post('/admin/settings/banners', [SettingsController::class, 'saveBanner']);
    $router->post('/admin/settings/banners/{id}', [SettingsController::class, 'updateBanner']);
    $router->post('/admin/settings/banners/{id}/delete', [SettingsController::class, 'deleteBanner']);
});


$router->group([
    'prefix' => '/employee',
    'middleware' => [AuthMiddleware::class, EmployeeMiddleware::class, CsrfMiddleware::class]
], function ($router) {
    $router->get('', [EmployeeDashboardController::class, 'index']);
    $router->get('/my-deliveries', [EmployeeDashboardController::class, 'myDeliveries']);
    $router->get('/pending', [EmployeeDashboardController::class, 'pending']);
    $router->get('/{id}', [EmployeeDashboardController::class, 'show']);
    $router->post('/{id}/take', [EmployeeDashboardController::class, 'take']);
    $router->patch('/{id}/status', [EmployeeDashboardController::class, 'updateStatus']);
    $router->post('/{id}/shipping-date', [EmployeeDashboardController::class, 'updateShippingDate']);
    $router->post('/{id}/delivery-date', [EmployeeDashboardController::class, 'updateDeliveryDate']);
});

$router->group(['middleware' => [AuthMiddleware::class, CsrfMiddleware::class]], function ($router) {
    $router->post('/access/logout', [AuthController::class, 'logout']);
});
