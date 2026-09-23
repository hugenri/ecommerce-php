<?php

declare(strict_types=1);

namespace App\Modules\Store\Presentation\Controllers;

use App\Core\Controller;
use App\Http\Request;
use App\Core\Validation\Validator;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;
use App\Modules\Customers\Application\UseCases\RequestCustomerPasswordResetUseCase;
use App\Modules\Customers\Application\UseCases\ResetCustomerPasswordUseCase;

class CustomerPasswordResetController extends Controller
{
    public function __construct(
        private RequestCustomerPasswordResetUseCase $requestCustomerPasswordReset,
        private ResetCustomerPasswordUseCase $resetCustomerPassword,
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
            return $this->performReset($data);
        }

        return $this->performRequest($data);
    }

    private function performRequest(array $data)
    {
        $errors = $this->validator->validate($data, [
            'email' => 'required|email',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        $this->requestCustomerPasswordReset->execute($data['email']);

        $_SESSION['flash_success'] = 'Si el correo existe, hemos enviado un enlace para restablecer la contraseña.';
        return $this->success(
            ['redirect' => '/reset-password'],
            'Si el correo existe, hemos enviado un enlace para restablecer la contraseña.'
        );
    }

    private function performReset(array $data)
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
            return $this->validationError($errors);
        }

        if ($this->resetCustomerPassword->execute($token, $data['password'])) {
            $_SESSION['flash_success'] = 'Tu contraseña ha sido actualizada. Inicia sesión con tu nueva contraseña.';
            return $this->success(
                ['redirect' => '/login'],
                'Tu contraseña ha sido actualizada. Inicia sesión con tu nueva contraseña.'
            );
        }

        return $this->error('El enlace de restablecimiento no es válido o ya fue utilizado.', 400);
    }
}