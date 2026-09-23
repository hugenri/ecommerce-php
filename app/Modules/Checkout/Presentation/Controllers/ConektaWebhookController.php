<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Presentation\Controllers;

use App\Core\Controller;
use App\Http\Request;
use App\Modules\Checkout\Application\UseCases\HandleConektaWebhookUseCase;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

/**
 * Recibe las notificaciones HTTP de Conekta y delega su procesamiento en
 * HandleConektaWebhookUseCase. Es una ruta pública (sin sesión ni CSRF).
 */
class ConektaWebhookController extends Controller
{
    public function __construct(
        private HandleConektaWebhookUseCase $handleConektaWebhook,
        private Request $request,
        SessionManagerInterface $sessionManager,
        Response $response,
    ) {
        parent::__construct($sessionManager, $response);
    }

    public function handle(): void
    {
        try {
            $this->handleConektaWebhook->execute(
                rawBody: $this->request->rawBody(),
                digestHeader: $this->request->header('digest') ?? '',
                payload: $this->request->post(),
            );

            $this->success([], 'Webhook procesado correctamente.');
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage(), 400);
        }
    }
}
