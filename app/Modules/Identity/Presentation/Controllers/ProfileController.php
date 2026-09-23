<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Controllers;

use App\Core\Controller;
use App\Modules\Identity\Application\UseCases\User\ChangeUserPasswordUseCase;
use App\Modules\Identity\Application\UseCases\User\GetUserUseCase;
use App\Modules\Identity\Application\UseCases\User\UpdateOwnProfileUseCase;
use App\Modules\Identity\Presentation\UserSerializer;
use App\Http\Request;
use App\Core\Validation\Validator;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class ProfileController extends Controller
{

    public function __construct(
        private GetUserUseCase $getUser,
        private UpdateOwnProfileUseCase $updateOwnProfile,
        private ChangeUserPasswordUseCase $changeUserPassword,
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
        $sessionUser = $this->sessionManager->get('user');
        $userId = (int) ($sessionUser['user_id'] ?? 0);

        $user = $this->getUser->execute($userId);

        if (!$user) {
            $this->redirect('/access/login');
            return;
        }

        return $this->view('profile/index', [
            'userName'  => $user->getName(),
            'userEmail' => $user->getEmail(),
            'userRole'  => $sessionUser['role'] ?? 'employee',
            'profile'   => $this->userSerializer->toArray($user),
        ]);
    }

    public function update()
    {
        $userId = (int) $this->sessionManager->get('user.user_id');
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'name'  => 'required|string|only_letters|min:4|max:30',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|numeric|digits:10',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        try {
            $user = $this->updateOwnProfile->execute(
                $userId,
                trim($data['name']),
                trim($data['email']),
                trim($data['phone'] ?? '') ?: null
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 400);
        }

        if (!$user) {
            return $this->notFound('Usuario no encontrado.');
        }

        $this->sessionManager->set('user.name', $user->getName());
        $this->sessionManager->set('user.email', $user->getEmail());

        return $this->success(
            ['user' => $this->userSerializer->toArray($user)],
            'Perfil actualizado exitosamente.'
        );
    }

    public function changePassword()
    {
        $userId = (int) $this->sessionManager->get('user.user_id');
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'current_password'           => 'required|format_password',
            'new_password'               => 'required|format_password',
            'new_password_confirmation'  => 'required|format_password',
        ]);

        if (($data['new_password'] ?? '') !== ($data['new_password_confirmation'] ?? '')) {
            $errors['new_password_confirmation'][] = 'Las contraseñas no coinciden.';
        }

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        $changed = $this->changeUserPassword->execute(
            $userId,
            $data['current_password'],
            $data['new_password']
        );

        if (!$changed) {
            return $this->error('Contraseña actual incorrecta o usuario no encontrado.', 400);
        }

        return $this->success(null, 'Contraseña actualizada exitosamente.');
    }
}
