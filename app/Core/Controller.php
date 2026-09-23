<?php

declare(strict_types=1);

namespace App\Core;

use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;
use App\Modules\Categories\Domain\CategoryRepositoryInterface;
use App\Modules\Settings\Application\Services\SettingsService;
use App\Modules\Subcategories\Domain\SubcategoryRepositoryInterface;

abstract class Controller
{
    protected View $view;

    private ?Container $container = null;

    public function __construct(
        protected SessionManagerInterface $sessionManager,
        protected Response $response
    ) {
        $this->view = new View();
    }

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    protected function view($view, $data = [], ?string $module = null): void
    {
        $data['csrfToken'] = $this->sessionManager->get('csrf_token') ?? '';

        if ($this->container !== null && !isset($data['siteSettings'])) {
            $data['siteSettings'] = $this->container->make(SettingsService::class)->getSiteSettings();
        }

        if ($this->container !== null && !isset($data['categories']) && !isset($data['catalogCategories'])) {
            $data['catalogCategories'] = $this->container->make(CategoryRepositoryInterface::class)->catalogCategories();
        }

        if ($this->container !== null && !isset($data['catalogSubcategories'])) {
            $catalogSubcategories = [];
            foreach ($this->container->make(SubcategoryRepositoryInterface::class)->catalogSubcategories() as $sub) {
                if ($sub->isActive()) {
                    $catalogSubcategories[$sub->getCategoryId()][] = $sub;
                }
            }
            $data['catalogSubcategories'] = $catalogSubcategories;
        }

        $this->view->render($module ?? $this->moduleName(), $view, $data);
    }

    protected function moduleName(): string
    {
        if (preg_match('/App\\\\Modules\\\\([^\\\\]+)\\\\/', static::class, $matches)) {
            return $matches[1];
        }

        throw new \RuntimeException('No se pudo determinar el módulo de ' . static::class);
    }

    protected function json($data, int $statusCode = 200): void
    {
        $this->response->json($data, $statusCode);
    }

    protected function success($data, string $message = 'OK', int $statusCode = 200, array $meta = []): void
    {
        $this->response->success($data, $message, $statusCode, $meta);
    }

    protected function error(string $message, int $statusCode = 400, $errors = null): void
    {
        $this->response->error($message, $statusCode, $errors);
    }

    protected function validationError($errors, string $message = 'Errores de validación.'): void
    {
        $this->response->validationError($errors, $message);
    }

    protected function badRequest(string $message = 'Solicitud inválida.'): void
    {
        $this->response->error($message, 400);
    }

    protected function notFound(string $message = 'Recurso no encontrado.'): void
    {
        $this->response->error($message, 404);
    }

    protected function redirect(string $url, int $statusCode = 302): void
    {
        $this->response->redirect($url, $statusCode);
    }
}
