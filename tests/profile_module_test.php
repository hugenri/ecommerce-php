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
use App\Modules\Identity\Application\UseCases\User\ChangeUserPasswordUseCase;
use App\Modules\Identity\Application\UseCases\Authentication\AuthenticateUserUseCase;
use App\Modules\Identity\Application\UseCases\User\UpdateOwnProfileUseCase;

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
$db->query("DELETE FROM users WHERE email = 'profile.own@example.com'");

$create = $container->make(CreateUserUseCase::class);
$user = $create->execute([
    'name' => 'Perfil Propio',
    'email' => 'profile.own@example.com',
    'password' => 'CurrentPass1!',
    'role' => 'employee',
    'is_active' => true,
]);

$userId = (int) $user->getId();
$originalEmail = $user->getEmail();
$originalRole = $user->getRole();

$updateOwn = $container->make(UpdateOwnProfileUseCase::class);
$updated = $updateOwn->execute($userId, 'Nombre Actualizado', $originalEmail, '5551234567');

echo "UpdateOwnProfileUseCase actualiza nombre: " . ($updated && $updated->getName() === 'Nombre Actualizado' ? 'PASS' : 'FAIL') . "\n";
echo "UpdateOwnProfileUseCase actualiza telefono: " . ($updated && $updated->getPhone() === '5551234567' ? 'PASS' : 'FAIL') . "\n";
echo "UpdateOwnProfileUseCase email sin cambios no falla: " . ($updated && $updated->getEmail() === $originalEmail ? 'PASS' : 'FAIL') . "\n";
echo "UpdateOwnProfileUseCase NO toca rol: " . ($updated && $updated->getRole() === $originalRole ? 'PASS' : 'FAIL') . "\n";

$emailUpdated = $updateOwn->execute($userId, 'Nombre Actualizado', 'profile.new@example.com', '5551234567');
echo "UpdateOwnProfileUseCase actualiza email: " . ($emailUpdated && $emailUpdated->getEmail() === 'profile.new@example.com' ? 'PASS' : 'FAIL') . "\n";

$dupEmail = 'profile.dup@example.com';
$db->query("DELETE FROM users WHERE email = 'profile.dup@example.com'");
$dupUser = $create->execute([
    'name' => 'Duplicado',
    'email' => $dupEmail,
    'password' => 'CurrentPass1!',
    'role' => 'employee',
    'is_active' => true,
]);

$duplicateRejected = false;
try {
    $updateOwn->execute($userId, 'Nombre Actualizado', $dupEmail, '5551234567');
} catch (\DomainException $e) {
    $duplicateRejected = str_contains($e->getMessage(), 'ya está registrado');
}
echo "UpdateOwnProfileUseCase rechaza email duplicado: " . ($duplicateRejected ? 'PASS' : 'FAIL') . "\n";

$missing = $updateOwn->execute(99999999, 'Otro', 'otro@example.com', '123');
echo "UpdateOwnProfileUseCase usuario inexistente -> null: " . ($missing === null ? 'PASS' : 'FAIL') . "\n";

$changePw = $container->make(ChangeUserPasswordUseCase::class);
$ok = $changePw->execute($userId, 'CurrentPass1!', 'NewPass123$');
echo "ChangeUserPasswordUseCase con contrasena actual correcta: " . ($ok ? 'PASS' : 'FAIL') . "\n";

$bad = $changePw->execute($userId, 'WrongPass1!', 'AnotherPass1!');
echo "ChangeUserPasswordUseCase rechaza contrasena actual incorrecta: " . ($bad === false ? 'PASS' : 'FAIL') . "\n";

$auth = $container->make(AuthenticateUserUseCase::class);
$login = $auth->execute('profile.new@example.com', 'NewPass123$', '127.0.0.1', 'test');
echo "Login con nueva contrasena aceptado: " . ($login !== null && $login->getUser() !== null ? 'PASS' : 'FAIL') . "\n";

$db->query("DELETE FROM users WHERE email IN ('profile.own@example.com', 'profile.new@example.com', 'profile.dup@example.com')");
echo "\n=== INTEGRACION MI PERFIL OK ===\n";
