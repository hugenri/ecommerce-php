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
use App\Modules\Identity\Application\UseCases\User\CreateUserUseCase;
use App\Modules\Identity\Application\UseCases\User\ChangeUserRoleUseCase;
use App\Modules\Identity\Application\UseCases\Authentication\AuthenticateUserUseCase;

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

$db = $container->make(Database::class);
$db->query("DELETE FROM users WHERE email = 'employee.role@example.com'");

$create = $container->make(CreateUserUseCase::class);
$user = $create->execute([
    'name' => 'Empleado Rol',
    'email' => 'employee.role@example.com',
    'password' => 'password123',
    'role' => 'employee',
    'is_active' => true,
]);

echo "CreateUserUseCase rol employee: " . ($user->getRole() === 'employee' ? 'PASS' : 'FAIL') . "\n";

$changeRole = $container->make(ChangeUserRoleUseCase::class);
$updated = $changeRole->execute((int) $user->getId(), 'employee');
echo "ChangeUserRoleUseCase -> employee: " . ($updated && $updated->getRole() === 'employee' ? 'PASS' : 'FAIL') . "\n";

$auth = $container->make(AuthenticateUserUseCase::class);
$result = $auth->execute('employee.role@example.com', 'password123', '127.0.0.1', 'test');
$authUser = $result->getUser();
$redirect = $authUser->hasRole('employee') ? '/employee' : '/admin';
echo "Login employee redirect: " . ($redirect === '/employee' ? 'PASS' : 'FAIL') . " (redirect={$redirect})\n";

$db->query("DELETE FROM users WHERE email = 'employee.role@example.com'");
echo "\n=== INTEGRACION IDENTITY OK ===\n";
