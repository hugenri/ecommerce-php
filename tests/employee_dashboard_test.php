<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Container;
use App\Core\SessionManager;
use App\Core\Database\Database;
use App\Core\Security\PasswordHasher;
use App\Core\Security\TokenGenerator;
use App\Core\Session\Store\SessionStore;
use App\Core\Http\Response;
use App\Framework\Session\SessionManagerInterface;
use App\Framework\Session\Store\SessionStoreInterface;
use App\Framework\Security\TokenGeneratorInterface;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Identity\Domain\PasswordHasherInterface;
use App\Modules\Identity\Persistence\UserRepository;
use App\Modules\Deliveries\Domain\DeliveryRepositoryInterface;
use App\Modules\Deliveries\Persistence\DeliveryRepository;
use App\Modules\EmployeeDashboard\Domain\EmployeeDashboardRepositoryInterface;
use App\Modules\EmployeeDashboard\Persistence\EmployeeDashboardRepository;
use App\Modules\EmployeeDashboard\Application\GetEmployeeDashboardSummaryUseCase;
use App\Modules\EmployeeDashboard\Application\ListMyDeliveriesUseCase;
use App\Modules\EmployeeDashboard\Application\ListPendingDeliveriesUseCase;
use App\Modules\EmployeeDashboard\Application\TakeDeliveryUseCase;
use App\Modules\EmployeeDashboard\Application\GetDeliveryUseCase;
use App\Modules\EmployeeDashboard\Application\UpdateDeliveryStatusUseCase;
use App\Modules\EmployeeDashboard\Application\RegisterShippingDateUseCase;
use App\Modules\EmployeeDashboard\Application\RegisterDeliveryDateUseCase;
use App\Modules\EmployeeDashboard\Presentation\Controllers\EmployeeDashboardController;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$container = new Container();
$container->singleton(Database::class);
$container->bind(UserRepositoryInterface::class, UserRepository::class);
$container->bind(PasswordHasherInterface::class, PasswordHasher::class);
$container->bind(TokenGeneratorInterface::class, TokenGenerator::class);
$container->bind(SessionStoreInterface::class, SessionStore::class);
$container->singleton(SessionManagerInterface::class, SessionManager::class);
$container->singleton(Response::class);
$container->bind(DeliveryRepositoryInterface::class, DeliveryRepository::class);
$container->bind(EmployeeDashboardRepositoryInterface::class, EmployeeDashboardRepository::class);

$db = $container->make(Database::class);

$db->query("DELETE FROM deliveries WHERE delivery_id > 1");
$db->query("DELETE FROM users WHERE email LIKE 'employee.test%@example.com'");

$employee = $container->make(UserRepositoryInterface::class)->save(new \App\Modules\Identity\Domain\User(
    id: null,
    name: 'Empleado Test',
    email: 'employee.test@example.com',
    password: (new PasswordHasher())->hash('password123'),
    role: 'employee',
    isActive: true,
));

$employeeId = (int) $employee->getId();
echo "Empleado creado: id={$employeeId}, role={$employee->getRole()}\n";

$summary = $container->make(GetEmployeeDashboardSummaryUseCase::class)->execute($employeeId);
echo "Summary inicial: " . json_encode($summary) . "\n";

$pending = $container->make(ListPendingDeliveriesUseCase::class)->execute(1, 10);
echo "Pendientes (sin empleado): " . count($pending['data']) . "\n";

$delivery = $pending['data'][0] ?? null;
if (!$delivery) {
    echo "NO HAY ENTREGAS PENDIENTES para probar TakeDelivery\n";
    exit(1);
}
$deliveryId = (int) $delivery['delivery_id'];

$take = $container->make(TakeDeliveryUseCase::class);
$take->execute($deliveryId, $employeeId);
echo "TakeDelivery OK: delivery={$deliveryId} asignada a empleado {$employeeId}\n";

$takeTaken = false;
try {
    $take->execute($deliveryId, $employeeId);
} catch (\DomainException $e) {
    $takeTaken = true;
    echo "TakeDelivery rechazada (ya asignada): {$e->getMessage()}\n";
}
if (!$takeTaken) {
    echo "FALLO: se permitio tomar una entrega ya asignada\n";
    exit(1);
}

$mine = $container->make(ListMyDeliveriesUseCase::class)->execute($employeeId, 1, 10);
echo "Mis entregas: " . count($mine['data']) . "\n";

$summary2 = $container->make(GetEmployeeDashboardSummaryUseCase::class)->execute($employeeId);
echo "Summary tras tomar: " . json_encode($summary2) . "\n";

$get = $container->make(GetDeliveryUseCase::class);
$detail = $get->execute($deliveryId, $employeeId);
echo "GetDelivery OK: sale_code={$detail['delivery']['sale_code']}, productos=" . count($detail['products']) . "\n";

$getForbidden = false;
try {
    $get->execute($deliveryId, $employeeId + 5000);
} catch (\DomainException $e) {
    $getForbidden = true;
    echo "GetDelivery rechazada (ajena): {$e->getMessage()}\n";
}
if (!$getForbidden) {
    echo "FALLO: empleado vio entrega de otro\n";
    exit(1);
}

$update = $container->make(UpdateDeliveryStatusUseCase::class);

$backward = false;
try {
    $update->execute($deliveryId, 'pending', $employeeId);
} catch (\DomainException $e) {
    $backward = true;
    echo "Update rechazado (pending): {$e->getMessage()}\n";
}
if (!$backward) {
    echo "FALLO: se permitio regresar a pending\n";
    exit(1);
}

$cancel = false;
try {
    $update->execute($deliveryId, 'cancelled', $employeeId);
} catch (\DomainException $e) {
    $cancel = true;
    echo "Update rechazado (cancelled): {$e->getMessage()}\n";
}
if (!$cancel) {
    echo "FALLO: el empleado pudo cancelar\n";
    exit(1);
}

$update->execute($deliveryId, 'preparing', $employeeId);
echo "Update -> preparing OK\n";

$detailAfter = $get->execute($deliveryId, $employeeId);
if (($detailAfter['delivery']['status'] ?? '') !== 'preparing') {
    echo "FALLO: el estado no cambio a preparing\n";
    exit(1);
}

$skip = false;
try {
    $update->execute($deliveryId, 'delivered', $employeeId);
} catch (\DomainException $e) {
    $skip = true;
    echo "Update rechazado (saltar shipped): {$e->getMessage()}\n";
}
if (!$skip) {
    echo "FALLO: se permitio saltar de preparing a delivered\n";
    exit(1);
}

$update->execute($deliveryId, 'shipped', $employeeId);
echo "Update -> shipped OK\n";
$d = $get->execute($deliveryId, $employeeId);
if (empty($d['delivery']['shipping_date'])) {
    echo "FALLO: shipping_date no se registro automaticamente\n";
    exit(1);
}
echo "shipping_date auto-registrado: {$d['delivery']['shipping_date']}\n";

$update->execute($deliveryId, 'delivered', $employeeId);
echo "Update -> delivered OK\n";
$d = $get->execute($deliveryId, $employeeId);
if (empty($d['delivery']['delivery_date'])) {
    echo "FALLO: delivery_date no se registro automaticamente\n";
    exit(1);
}
echo "delivery_date auto-registrado: {$d['delivery']['delivery_date']}\n";

$registerShipping = $container->make(RegisterShippingDateUseCase::class);
$registerShipping->execute($deliveryId, '2026-07-30 10:00:00', $employeeId);
echo "RegisterShippingDate OK\n";

$registerDelivery = $container->make(RegisterDeliveryDateUseCase::class);
$registerDelivery->execute($deliveryId, '2026-07-30 18:00:00', $employeeId);
echo "RegisterDeliveryDate OK\n";

$dateBackward = false;
try {
    $registerDelivery->execute($deliveryId, '2020-01-01 10:00:00', $employeeId);
} catch (\DomainException $e) {
    $dateBackward = true;
    echo "RegisterDeliveryDate rechazada (anterior a envio): {$e->getMessage()}\n";
}
if (!$dateBackward) {
    echo "FALLO: se registro fecha de entrega anterior al envio\n";
    exit(1);
}

$controller = $container->make(EmployeeDashboardController::class);
echo "Controller resuelto por contenedor: " . get_class($controller) . "\n";

$finalSummary = $container->make(GetEmployeeDashboardSummaryUseCase::class)->execute($employeeId);
echo "Summary final: " . json_encode($finalSummary) . "\n";

echo "\n=== TODAS LAS PRUEBAS PASARON ===\n";
