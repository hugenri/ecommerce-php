<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Controllers;

use App\Core\Controller;
use App\Modules\Identity\Application\UseCases\User\ListUsersUseCase;
use App\Modules\Identity\Application\UseCases\User\CreateUserUseCase;
use App\Modules\Identity\Application\UseCases\User\GetUserUseCase;
use App\Modules\Identity\Application\UseCases\User\UpdateUserUseCase;
use App\Modules\Identity\Application\UseCases\User\DeleteUserUseCase;
use App\Modules\Identity\Application\UseCases\User\ToggleUserActiveUseCase;
use App\Modules\Identity\Application\UseCases\User\ChangeUserRoleUseCase;
use App\Modules\Identity\Application\UseCases\User\AdminResetUserPasswordUseCase;
use App\Modules\Identity\Presentation\UserSerializer;
use App\Http\Request;
use App\Core\Validation\Validator;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class UserController extends Controller
{

    public function __construct(
        private ListUsersUseCase $listUsers,
        private CreateUserUseCase $createUser,
        private GetUserUseCase $getUser,
        private UpdateUserUseCase $updateUser,
        private DeleteUserUseCase $deleteUser,
        private ToggleUserActiveUseCase $toggleUserActive,
        private ChangeUserRoleUseCase $changeUserRole,
        private AdminResetUserPasswordUseCase $resetUserPassword,
        private UserSerializer $userSerializer,
        private Validator $validator,
        private Request $request,
        SessionManagerInterface $sessionManager,
        Response $response
    ) {
        parent::__construct($sessionManager, $response);
    }

    public function index()
    {
        $user = $this->sessionManager->get('user');
        return $this->view('users/index', [
            'userName' => $user['name'] ?? '',
            'userEmail' => $user['email'] ?? '',
        ]);
    }

    public function data()
    {
        $page = max(1, (int) ($this->request->get('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->get('per_page') ?? 10)));
        $filters = array_filter([
            'search' => $this->request->get('search'),
            'role' => $this->request->get('role'),
            'is_active' => $this->request->get('is_active') !== null ? filter_var($this->request->get('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null,
            'sort_by' => $this->request->get('sort_by') ?? 'created_at',
            'sort_order' => strtoupper($this->request->get('sort_order') ?? 'DESC'),
        ], fn($v) => $v !== null && $v !== '');

        $result = $this->listUsers->execute($page, $perPage, $filters['search'] ?? '', $filters['sort_by'] ?? 'user_id', $filters['sort_order'] ?? 'ASC', $filters);

        return $this->success(
            array_map(fn($user) => $this->userSerializer->toArray($user), $result['data']),
            'Lista de usuarios',
            200,
            $result['meta']
        );
    }

    public function show(int $id)
    {
        $user = $this->getUser->execute($id);

        if (!$user) {
            return $this->notFound('Usuario no encontrado.');
        }

        return $this->success($this->userSerializer->toArray($user));
    }

    public function store()
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'name'     => 'required|string|only_letters|min:4|max:30',
            'email'    => 'required|email',
            'password' => 'required|format_password',
            'role'     => 'required|in:admin,employee',
            'phone'    => 'nullable|numeric|digits:10',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        try {
            $user = $this->createUser->execute($data);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 400);
        }

        return $this->success(['user' => $this->userSerializer->toArray($user)], 'Usuario creado exitosamente.', 201);
    }

    public function update(int $id)
    {
        $data = $this->request->post();

        $rules = [
            'name'  => 'sometimes|string|only_letters|min:4|max:30',
            'email' => 'sometimes|email',
            'phone' => 'sometimes|numeric|digits:10',
        ];

        $errors = $this->validator->validate($data, $rules);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        try {
            $user = $this->updateUser->execute($id, $data);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 400);
        }

        if (!$user) {
            return $this->notFound('Usuario no encontrado.');
        }

        return $this->success(['user' => $this->userSerializer->toArray($user)], 'Usuario actualizado exitosamente.');
    }

    public function destroy(int $id)
    {
        try {
            $deleted = $this->deleteUser->execute($id);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 400);
        }

        if (!$deleted) {
            return $this->notFound('Usuario no encontrado.');
        }

        return $this->success(null, 'Usuario eliminado exitosamente.');
    }

    public function toggleActive(int $id)
    {
        $sessionUser = $this->sessionManager->get('user');
        $actorId = (int) ($sessionUser['user_id'] ?? 0);

        try {
            $user = $this->toggleUserActive->execute($id, $actorId);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 400);
        }

        if (!$user) {
            return $this->notFound('Usuario no encontrado.');
        }

        $status = $user->isActive() ? 'activada' : 'desactivada';
        return $this->success(['user' => $this->userSerializer->toArray($user), 'is_active' => $user->isActive()], "Cuenta $status exitosamente.");
    }

    public function resetPassword(int $id)
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'new_password'              => 'required|format_password',
            'new_password_confirmation' => 'required|format_password',
        ]);

        if (($data['new_password'] ?? '') !== ($data['new_password_confirmation'] ?? '')) {
            $errors['new_password_confirmation'][] = 'Las contraseñas no coinciden.';
        }

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        $reset = $this->resetUserPassword->execute($id, $data['new_password']);

        if (!$reset) {
            return $this->notFound('Usuario no encontrado.');
        }

        return $this->success(null, 'Contraseña restablecida exitosamente.');
    }
}
