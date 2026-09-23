<?php

declare(strict_types=1);

use App\Core\Container;
use App\Core\Database\Database;
use App\Core\Mail\MailService;
use App\Config\Config;
use App\Config\MailConfig;
use App\Core\Session\Store\SessionStore;
use App\Core\SessionManager;
use App\Core\Security\PasswordHasher;
use App\Core\Security\TokenGenerator;
use App\Core\Http\Response;
use App\Framework\Session\SessionManagerInterface;
use App\Framework\Session\Store\SessionStoreInterface;
use App\Framework\Security\TokenGeneratorInterface;
use App\Modules\Customers\Domain\Customer;
use App\Modules\Customers\Domain\CustomerRepositoryInterface;
use App\Modules\Customers\Domain\CustomerTokenRepositoryInterface;
use App\Modules\Customers\Persistence\CustomerRepository;
use App\Modules\Customers\Persistence\CustomerTokenRepository;
use App\Modules\Customers\Application\UseCases\LoginCustomerUseCase;
use App\Modules\Customers\Application\UseCases\RequestCustomerPasswordResetUseCase;
use App\Modules\Customers\Application\UseCases\ResetCustomerPasswordUseCase;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Identity\Domain\UserTokenRepositoryInterface;
use App\Modules\Identity\Domain\PasswordHasherInterface;
use App\Modules\Identity\Persistence\UserRepository;
use App\Modules\Identity\Persistence\UserTokenRepository;
use App\Modules\Identity\Application\UseCases\User\CreateUserUseCase;
use App\Modules\Identity\Application\UseCases\Authentication\AuthenticateUserUseCase;
use App\Modules\Identity\Application\UseCases\Authentication\RequestPasswordResetUseCase;
use App\Modules\Identity\Application\UseCases\Authentication\ResetPasswordUseCase;

require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();

class CapturingMailService extends MailService
{
    public string $lastHtml = '';

    public function __construct()
    {
        parent::__construct(new MailConfig(new Config()));
    }

    public function send(string $to, string $subject, string $htmlBody, ?string $from = null, ?string $fromName = null): bool
    {
        $this->lastHtml = $htmlBody;
        return true;
    }
}

function fail(string $msg): never
{
    echo "FAIL: $msg\n";
    exit(1);
}

$c = new Container();
$capturingMail = new CapturingMailService();
$c->instance(MailService::class, $capturingMail);
$c->singleton(Database::class);
$c->singleton(SessionManagerInterface::class, SessionManager::class);
$c->singleton(Response::class);
$c->singleton(\App\Http\Request::class);
$c->bind(CustomerRepositoryInterface::class, CustomerRepository::class);
$c->bind(CustomerTokenRepositoryInterface::class, CustomerTokenRepository::class);
$c->bind(UserRepositoryInterface::class, UserRepository::class);
$c->bind(UserTokenRepositoryInterface::class, UserTokenRepository::class);
$c->bind(PasswordHasherInterface::class, PasswordHasher::class);
$c->bind(TokenGeneratorInterface::class, TokenGenerator::class);
$c->bind(SessionStoreInterface::class, SessionStore::class);

$db = $c->make(Database::class);
$hasher = $c->make(PasswordHasherInterface::class);
$customerRepo = $c->make(CustomerRepositoryInterface::class);
$customerTokenRepo = $c->make(CustomerTokenRepositoryInterface::class);
$userRepo = $c->make(UserRepositoryInterface::class);
$userTokenRepo = $c->make(UserTokenRepositoryInterface::class);

$CUSTOMER_EMAIL = 'reset.customer@example.com';
$ADMIN_EMAIL = 'reset.admin@example.com';
$EMPLOYEE_EMAIL = 'reset.employee@example.com';

$db->query("DELETE FROM users WHERE email IN ('$ADMIN_EMAIL','$EMPLOYEE_EMAIL')");
$db->query("DELETE FROM customers WHERE email = '$CUSTOMER_EMAIL'");
$db->query("DELETE ut FROM user_tokens ut JOIN users u ON u.user_id = ut.user_id WHERE u.email IN ('$ADMIN_EMAIL','$EMPLOYEE_EMAIL')");
$db->query("DELETE ct FROM customer_tokens ct JOIN customers cu ON cu.customer_id = ct.customer_id WHERE cu.email = '$CUSTOMER_EMAIL'");

// ───── 1. CLIENTE ─────
echo "== Cliente ==\n";
$customer = new Customer(
    customerId: null,
    firstName: 'Reset',
    lastNamePaternal: 'Cliente',
    email: $CUSTOMER_EMAIL,
    password: $hasher->hash('OldPass123!x'),
    active: true,
    createdAt: new \DateTimeImmutable(),
    updatedAt: new \DateTimeImmutable(),
    emailVerifiedAt: new \DateTimeImmutable(),
);
$customer = $customerRepo->create($customer);
$customerId = (int) $customer->getCustomerId();

$c->make(RequestCustomerPasswordResetUseCase::class)->execute($CUSTOMER_EMAIL);
if (!preg_match('#/reset-password\?token=([^&\s<"]+)#', $capturingMail->lastHtml, $m)) {
    fail("Enlace de cliente no encontrado en el correo");
}
$customerToken = urldecode($m[1]);
if (str_contains($capturingMail->lastHtml, '/access/reset-password')) {
    fail("El enlace de cliente no debe apuntar a /access/");
}
echo "Link cliente: /reset-password?token=... (OK)\n";

if (!$c->make(ResetCustomerPasswordUseCase::class)->execute($customerToken, 'NewPass456!')) {
    fail('Reset de cliente no retornó true');
}
$login = $c->make(LoginCustomerUseCase::class)->execute($CUSTOMER_EMAIL, 'NewPass456!');
echo "Login cliente (nueva pass): " . ($login->isSuccess() ? 'PASS' : 'FAIL') . "\n";
if (!$login->isSuccess()) {
    fail('El cliente no puede iniciar sesión con la nueva contraseña');
}

// Aislamiento: el token de cliente NO debe servir para el reset de administrador
if ($c->make(ResetPasswordUseCase::class)->execute($customerToken, 'Hack0123?')) {
    fail('El token de cliente no debe ser válido en el flujo admin');
}
echo "Token cliente NO válido en flujo admin (OK)\n";

// ───── 2. ADMIN ─────────────────────────────────────
echo "== Administrador ==\n";
$createUser = $c->make(CreateUserUseCase::class);
$admin = $createUser->execute([
    'name' => 'Reset Admin',
    'email' => $ADMIN_EMAIL,
    'password' => 'OldPass123',
    'role' => 'admin',
    'is_active' => true,
]);

foreach (new \ArrayIterator([]) as $ignored) {} // noop
$requestReset = $c->make(RequestPasswordResetUseCase::class);
$requestReset->execute($ADMIN_EMAIL);
if (!preg_match('#/access/reset-password\?token=([^&\s<"]+)#', $capturingMail->lastHtml, $m2)) {
    fail('Enlace admin no encontrado en el correo');
}
$adminToken = urldecode($m2[1]);
echo "Link admin: /access/reset-password?token=... (OK)\n";

if (!$c->make(ResetPasswordUseCase::class)->execute($adminToken, 'NewAdmin456!')) {
    fail('Reset del admin no retornó true');
}

// token admin no debe servir en el flujo cliente
if ($c->make(ResetCustomerPasswordUseCase::class)->execute($adminToken, 'Hack123?')) {
    fail('El token de admin NO debe ser válido para el cliente');
}
echo "Token admin NO válido en flujo cliente (OK)\n";

$authAdmin = $c->make(AuthenticateUserUseCase::class)->execute($ADMIN_EMAIL, 'NewAdmin456!', '127.0.0.1', 'test');
if (!$authAdmin || $authAdmin->getUser()->getRole() !== 'admin') {
    fail('El admin no puede iniciar sesión con la nueva contraseña');
}
echo "Login admin (nueva pass): PASS (role=admin)\n";

// Isolate admin token single-use
if ($c->make(ResetPasswordUseCase::class)->execute($adminToken, 'Again123!')) {
    fail('El token admin debería ser de un solo uso (ya usado)');
}
echo "Token admin de un solo uso (OK)\n";

// ───── 3. EMPLEADO ──────────────────────────────────
echo "== Empleado ==\n";
$employee = $createUser->execute([
    'name' => 'Reset Empleado',
    'email' => $EMPLOYEE_EMAIL,
    'password' => 'OldPass123',
    'role' => 'employee',
    'is_active' => true,
]);
$requestReset->execute($EMPLOYEE_EMAIL);
if (!preg_match('#/access/reset-password\?token=([^&\s<"]+)#', $capturingMail->lastHtml, $m3)) {
    fail('Enlace empleado no encontrado');
}
$employeeToken = urldecode($m3[1]);
if (!$c->make(ResetPasswordUseCase::class)->execute($employeeToken, 'Employee456!')) {
    fail('Reset del empleado no retornó true');
}
$authEmp = $c->make(AuthenticateUserUseCase::class)->execute($EMPLOYEE_EMAIL, 'Employee456!', '127.0.0.1', 'test');
if (!$authEmp || $authEmp->getUser()->getRole() !== 'employee') {
    fail('El empleado no puede iniciar sesión con la nueva contraseña');
}
echo "Login empleado (nueva pass): PASS (role=employee)\n";

// cleanup
$db->query("DELETE FROM users WHERE email IN ('$ADMIN_EMAIL','$EMPLOYEE_EMAIL')");
$db->query("DELETE FROM customers WHERE email = '$CUSTOMER_EMAIL'");

echo "\n=== RESET FLOW OK ===\n";