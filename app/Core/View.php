<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class View
{
    protected string $appPath;

    public function __construct(?string $appPath = null)
    {
        $this->appPath = $appPath ?? dirname(__DIR__);
    }

    public function appPath(): string
    {
        return $this->appPath;
    }

    public function sharedViewsPath(): string
    {
        return $this->appPath . '/Shared/Views';
    }

    public function moduleViewsPath(string $module): string
    {
        return $this->appPath . '/Modules/' . $module . '/Presentation/Views';
    }

    public function resolve(string $module, string $view): string
    {
        $path = $this->moduleViewsPath($module) . '/' . ltrim($view, '/') . '.php';

        if (!is_file($path)) {
            throw new RuntimeException(
                "Vista no encontrada: {$path} (módulo: {$module})"
            );
        }

        return $path;
    }

    public function render(string $module, string $view, array $data = []): void
    {
        $data['sharedViewsPath'] = $this->sharedViewsPath();

        extract($data, EXTR_SKIP);
        require $this->resolve($module, $view);
    }
}
