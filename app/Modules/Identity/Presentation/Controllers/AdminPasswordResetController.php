<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Controllers;

use App\Core\Controller;
use App\Http\Request;
use App\Core\Validation\Validator;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;
use App\Modules\Identity\Application\UseCases\Authentication\RequestPasswordResetUseCase;
use App\Modules\Identity\Application\UseCases\Authentication\ResetPasswordUseCase;

class AdminPasswordResetController extends Controller
{
    public function __construct(
        private RequestPasswordResetUseCase $requestPasswordReset,
        private ResetPasswordUseCase $resetPassword,
        private Validator $validator,
        private Request $request,
        SessionManagerInterface $sessionManager,
        Response $response
    ) {
        parent::__construct($sessionManager, $response);
    }

    public function show()
    {
        $token = (string) $this->request->get('token', '');

        if ($token !== '') {
            $this->view('reset-password', ['token' => $token]);
            return;
        }

        $this->view('forgot-password');
    }

    public function submit()
    {
        $data = $this->request->post();

        if (isset($data['password']) || isset($data['token'])) {
            $this->performReset($data);
            return;
        }

        $this->performRequest($data);
    }

    private function performRequest(array $data): void
    {
        $errors = $this->validator->validate($data, [
            'email' => 'required|email',
        ]);

        if ($this->validator->hasErrors($errors)) {
            $this->validationError($errors);
            return;
        }

        $this->requestPasswordReset->execute($data['email']);

        $this->success(null, 'Si el correo existe, hemos enviado un enlace para restablecer la contraseña.');
    }

    private function performReset(array $data): void
    {
        $token = (string) ($data['token'] ?? '');

        $errors = $this->validator->validate($data, [
            'token'    => 'required|string',
            'password' => 'required|format_password',
        ]);

        if (($data['password'] ?? '') !== ($data['password_confirmation'] ?? '')) {
            $errors['password_confirmation'][] = 'Las contraseñas no coinciden.';
        }

        if ($this->validator->hasErrors($errors)) {
            $this->validationError($errors);
            return;
        }

        if ($this->resetPassword->execute($token, $data['password'])) {
            $this->success(
                ['redirect' => '/access/login'],
                'Tu contraseña ha sido actualizada. Inicia sesión con tu nueva contraseña.'
            );
            return;
        }

        $this->error('El enlace de restablecimiento no es válido o ya fue utilizado.');
    }
}