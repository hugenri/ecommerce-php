<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Container;
use App\Core\Security\PasswordHasher;
use App\Core\Security\TokenGenerator;
use App\Core\SessionManager;
use App\Core\Session\Store\SessionStore;
use App\Core\Database\Database;
use App\Core\Http\Response;
use App\Framework\Session\SessionManagerInterface;
use App\Framework\Session\Store\SessionStoreInterface;
use App\Framework\Security\TokenGeneratorInterface;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Identity\Domain\PasswordHasherInterface;
use App\Modules\Identity\Persistence\UserRepository;
use App\Modules\Identity\Application\UseCases\User\CreateUserUseCase;
use App\Modules\Identity\Application\UseCases\User\AdminResetUserPasswordUseCase;
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
$db->query("DELETE FROM users WHERE email IN ('reset.target@example.com', 'reset.removed@example.com')");

$create = $container->make(CreateUserUseCase::class);
$target = $create->execute([
    'name' => 'Target Reset',
    'email' => 'reset.target@example.com',
    'password' => 'OldPassword1!',
    'role' => 'employee',
    'is_active' => true,
]);

$removed = $create->execute([
    'name' => 'Removed Reset',
    'email' => 'reset.removed@example.com',
    'password' => 'OldPassword1!',
    'role' => 'employee',
    'is_active' => true,
]);

$hasher = $container->make(PasswordHasherInterface::class);
$reset = $container->make(AdminResetUserPasswordUseCase::class);
$auth = $container->make(AuthenticateUserUseCase::class);

$targetId = (int) $target->getId();

$ok = $reset->execute($targetId, 'NewPassword9$');
echo "AdminResetUserPasswordUseCase exitoso: " . ($ok ? 'PASS' : 'FAIL') . "\n";

$oldLogin = $auth->execute('reset.target@example.com', 'OldPassword1!', '127.0.0.1', 'test');
echo "Login con contraseña antigua rechazado: " . ($oldLogin === null ? 'PASS' : 'FAIL') . "\n";

$newLogin = $auth->execute('reset.target@example.com', 'NewPassword9$', '127.0.0.1', 'test');
echo "Login con nueva contraseña aceptado: " . ($newLogin !== null && $newLogin->getUser() !== null ? 'PASS' : 'FAIL') . "\n";

$bad = $reset->execute(99999999, 'NewPassword9$');
echo "Reset de usuario inexistente -> false: " . ($bad === false ? 'PASS' : 'FAIL') . "\n";

$db->query("DELETE FROM users WHERE email IN ('reset.target@example.com', 'reset.removed@example.com')");
echo "\n=== INTEGRACION ADMIN RESET OK ===\n";
