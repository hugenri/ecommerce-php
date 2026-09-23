<?php

declare(strict_types=1);

namespace App\Modules\Customers\Presentation\Controllers;

use App\Core\Controller;
use App\Modules\Customers\Application\UseCases\RegisterCustomerUseCase;
use App\Modules\Customers\Application\UseCases\LoginCustomerUseCase;
use App\Modules\Customers\Application\UseCases\LogoutCustomerUseCase;
use App\Modules\Customers\Application\UseCases\MergeGuestCartUseCase;
use App\Modules\Customers\Application\UseCases\VerifyCustomerEmailUseCase;
use App\Modules\Customers\Application\UseCases\ResendCustomerVerificationEmailUseCase;
use App\Modules\Customers\Domain\LoginResult;
use App\Modules\Customers\Presentation\CustomerSerializer;
use App\Http\Request;
use App\Core\Validation\Validator;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class CustomerAuthController extends Controller
{

    public function __construct(
        private RegisterCustomerUseCase $registerCustomer,
        private LoginCustomerUseCase $loginCustomer,
        private LogoutCustomerUseCase $logoutCustomer,
        private MergeGuestCartUseCase $mergeGuestCart,
        private VerifyCustomerEmailUseCase $verifyCustomerEmail,
        private ResendCustomerVerificationEmailUseCase $resendVerificationEmail,
        private CustomerSerializer $serializer,
        private Validator $validator,
        private Request $request,
        SessionManagerInterface $sessionManager,
        Response $response
    ) {
        parent::__construct($sessionManager, $response);
    }

    // ──── Register ──────────────────────────────────────

    public function showRegister()
    {
        $customer = $this->sessionManager->get('customer');
        if ($customer) {
            $this->redirect('/account');
            return;
        }
        $this->view('register');
    }

    public function register()
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'first_name'       => 'required|string|only_letters|min:3|max:20',
            'last_name_paternal' => 'required|string|only_letters|min:3|max:20',
            'last_name_maternal' => 'nullable|string|only_letters|min:3|max:20',
            'email'            => 'required|email',
            'password'         => 'required|format_password',
            'phone'            => 'nullable|numeric|digits:10',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        try {
            $this->registerCustomer->execute(
                firstName: $data['first_name'],
                lastNamePaternal: $data['last_name_paternal'],
                email: $data['email'],
                password: $data['password'],
                lastNameMaternal: $data['last_name_maternal'] ?? null,
                phone: $data['phone'] ?? null,
            );

            $_SESSION['flash_success'] = 'Tu cuenta fue creada correctamente. Hemos enviado un correo con el enlace para verificarla.';
            return $this->success(
                ['redirect' => '/login'],
                'Tu cuenta fue creada correctamente.'
            );
        } catch (\DomainException $e) {
            return $this->validationError(['email' => [$e->getMessage()]]);
        }
    }

    // ──── Login ─────────────────────────────────────────

    public function showLogin()
    {
        $customer = $this->sessionManager->get('customer');
        if ($customer) {
            $this->redirect('/account');
            return;
        }

        $redirect = $_GET['redirect'] ?? null;
        if ($redirect && ! $this->sessionManager->has('redirect_after_login')) {
            $this->sessionManager->set('redirect_after_login', $redirect);
        }

        $this->view('login');
    }

    public function login()
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'email'    => 'required|email',
            'password' => 'required|format_password',
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

        $result = $this->loginCustomer->execute(
            $data['email'],
            $data['password']
        );

        if ($result->status() === LoginResult::EMAIL_NOT_VERIFIED) {
            return $this->error(
                'Tu cuenta aún no ha sido verificada. Revisa el correo que te enviamos para activar tu cuenta.',
                403
            );
        }

        if ($result->status() === LoginResult::ACCOUNT_DISABLED) {
            return $this->error('Cuenta desactivada.', 403);
        }

        if (!$result->isSuccess()) {
            return $this->error('Credenciales inválidas.', 401);
        }

        $customer = $result->getCustomer();

        $this->sessionManager->regenerateIdForced();

        $this->sessionManager->set('customer', $this->serializer->toArray($customer));
        $this->sessionManager->set('customer.id', $customer->getCustomerId());
        $this->sessionManager->set('customer.name', $customer->getFullName());
        $this->sessionManager->set('customer.email', $customer->getEmail());
        $this->sessionManager->set('customer.last_activity', time());

        $this->mergeGuestCart->execute((int) $customer->getCustomerId());

        $redirect = $this->sessionManager->get('redirect_after_login');
        if (
            !is_string($redirect)
            || !str_starts_with($redirect, '/')
            || str_starts_with($redirect, '//')
        ) {
            $redirect = '/account';
        }

        $this->sessionManager->remove('redirect_after_login');

        return $this->success([
            'redirect' => $redirect,
        ], 'Login exitoso');
    }

    // ──── Logout ────────────────────────────────────────

    public function logout()
    {
        $this->logoutCustomer->execute();
        $this->sessionManager->remove('customer');
        $this->sessionManager->remove('customer.id');
        $this->sessionManager->remove('customer.name');
        $this->sessionManager->remove('customer.email');
        $this->sessionManager->remove('customer.last_activity');
        $this->sessionManager->remove('redirect_after_login');
        $this->redirect('/');
    }

    // ──── Email verification ────────────────────────────

    public function verifyEmail()
    {
        $token = (string) $this->request->get('token', '');

        try {
            $this->verifyCustomerEmail->execute($token);
            $this->view('verify-email-success');
        } catch (\DomainException $e) {
            $this->view('verify-email-invalid');
        }
    }

    public function resendVerification()
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'email' => 'required|email',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        $this->resendVerificationEmail->execute($data['email']);

        $message = 'Si el correo existe y aún no ha sido verificado, hemos enviado un nuevo enlace de verificación.';
        $_SESSION['flash_success'] = $message;

        return $this->success(['redirect' => '/login'], $message);
    }
}
