<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Controllers;

use App\Core\Controller;
use App\Modules\Identity\Application\UseCases\Authentication\AuthenticateUserUseCase;
use App\Modules\Identity\Application\UseCases\Authentication\RegisterUserUseCase;
use App\Modules\Identity\Application\UseCases\Authentication\LogoutUserUseCase;
use App\Modules\Identity\Presentation\UserSerializer;
use App\Http\Request;
use App\Core\Validation\Validator;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class AuthController extends Controller
{

    public function __construct(
        private AuthenticateUserUseCase $authenticateUser,
        private RegisterUserUseCase $registerUser,
        private LogoutUserUseCase $logoutUser,
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
        $this->view('index');
    }

    public function login()
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($this->validator->hasErrors($errors)) {

            if (!empty($errors['email'])) {
                $errors['email'] = [
                    'El correo electrónico no tiene un formato válido.'
                ];
            }

            if (!empty($errors['password'])) {
                $errors['password'] = [
                    'La contraseña no es válida.'
                ];
            }

            return $this->validationError($errors);
        }

        try {
            $result = $this->authenticateUser->execute(
                $data['email'],
                $data['password'],
                $this->request->ip(),
                $this->request->userAgent()
            );

            if (!$result) {
                return $this->error('Credenciales inválidas', 401);
            }

            $user = $result->getUser();
            if ($user->hasRole('employee')) {
                $redirect = '/employee';
            } else {
                $redirect = '/admin';
            }

            return $this->success([
                'user' => $result->getSessionData(),
                'redirect' => $redirect,
            ], 'Login exitoso');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 403);
        }
    }

    public function logout()
    {
        $userId = $this->sessionManager->get('user.user_id');
        $sessionId = $this->sessionManager->getId();

        $this->logoutUser->execute($userId, $sessionId);
        $this->sessionManager->destroy();

        $this->success(['redirect' => '/access/login'], 'Sesión cerrada.');
    }

    public function register()
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'name'     => 'required|string|only_letters|min:4|max:30',
            'email'    => 'required|email',
            'password' => 'required|format_password',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        try {
            $user = $this->registerUser->execute($data);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 400);
        }

        return $this->success(['user' => $this->userSerializer->toArray($user)], 'Usuario creado', 201);
    }
}
