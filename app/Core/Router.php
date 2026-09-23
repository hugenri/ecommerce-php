<?php

namespace App\Core;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\MethodNotAllowedException;

class Router
{
    protected $routes = [];
    protected $namedRoutes = [];
    protected $basePath = '';
    protected $groupMiddleware = [];
    protected $groupPrefix = '';
    protected $container;

    // Verbos HTTP soportados
    protected $verbs = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    public function __construct($container = null)
    {
        $this->container = $container;
    }

    public function add($method, $uri, $handler, $middleware = [])
    {
        $uri = $this->normalizeUri(
            $this->groupPrefix . $uri
        );

        if (is_array($handler) && isset($handler['as'])) {
            $this->namedRoutes[$handler['as']] = $uri;
        }

        $finalMiddleware = array_merge(
            $this->groupMiddleware,
            (array) $middleware
        );

        $this->routes[] = [
            'method' => strtoupper($method),
            'uri' => $uri,
            'handler' => $handler,
            'middleware' => $finalMiddleware
        ];

        return $this;
    }
    /**
     * Métodos para verbos HTTP específicos
     */
    public function get($uri, $handler, $middleware = [])
    {
        return $this->add('GET', $uri, $handler, $middleware);
    }

    public function post($uri, $handler, $middleware = [])
    {
        return $this->add('POST', $uri, $handler, $middleware);
    }

    public function put($uri, $handler, $middleware = [])
    {
        return $this->add('PUT', $uri, $handler, $middleware);
    }

    public function patch($uri, $handler, $middleware = [])
    {
        return $this->add('PATCH', $uri, $handler, $middleware);
    }

    public function delete($uri, $handler, $middleware = [])
    {
        return $this->add('DELETE', $uri, $handler, $middleware);
    }

    public function options($uri, $handler, $middleware = [])
    {
        return $this->add('OPTIONS', $uri, $handler, $middleware);
    }

    /**
     * Ruta que responde a múltiples verbos
     */
    public function match(array $methods, $uri, $handler, $middleware = [])
    {
        foreach ($methods as $method) {
            $this->add($method, $uri, $handler, $middleware);
        }
        return $this;
    }

    /**
     * Ruta que responde a todos los verbos
     */
    public function any($uri, $handler, $middleware = [])
    {
        return $this->match($this->verbs, $uri, $handler, $middleware);
    }

    /**
     * Grupo de rutas con configuración común
     */
    public function group(array $attributes, callable $callback)
    {
        $previousGroupPrefix = $this->groupPrefix;
        $previousGroupMiddleware = $this->groupMiddleware;

        // Aplicar prefijo del grupo
        if (isset($attributes['prefix'])) {
            $this->groupPrefix = $this->groupPrefix . $this->normalizeUri($attributes['prefix']);
        }

        // Aplicar middleware del grupo
        if (isset($attributes['middleware'])) {
            $this->groupMiddleware = array_merge($this->groupMiddleware, (array) $attributes['middleware']);
        }

        // Ejecutar callback del grupo
        call_user_func($callback, $this);

        // Restaurar valores anteriores
        $this->groupPrefix = $previousGroupPrefix;
        $this->groupMiddleware = $previousGroupMiddleware;
    }

    /**
     * Ejecuta el enrutador
     */
    public function dispatch($requestUri = null, $requestMethod = null)
    {
        $requestUri = $requestUri ?? $this->getCurrentUri();
        $requestMethod = $requestMethod ?? $this->getRequestMethod();

        $requestUri = $this->normalizeUri($requestUri);

        $matchedRoute = null;
        $params = [];

        // Buscar ruta que coincida
        foreach ($this->routes as $route) {
            if ($route['method'] === $requestMethod) {
                $pattern = $this->buildPatternFromUri($route['uri']);

                if (preg_match($pattern, $requestUri, $matches)) {
                    $matchedRoute = $route;

                    // Extraer parámetros
                    foreach ($matches as $key => $value) {
                        if (is_string($key)) {
                            $params[$key] = $value;
                        }
                    }

                    break;
                }
            }
        }

        if ($matchedRoute) {
            // Contexto de la petición para middleware
            $requestContext = [
                'uri' => $requestUri,
                'method' => $requestMethod,
                'params' => $params,
                'route' => $matchedRoute,
            ];

            // Ejecutar middleware
            $this->runMiddleware($matchedRoute['middleware'], $requestContext);

            // Ejecutar handler (solo recibe parámetros de ruta)
            return $this->runHandler($matchedRoute['handler'], $params);
        }

        // Verificar si el método no está permitido para la URI
        $allowedMethods = [];
        foreach ($this->routes as $route) {
            if ($this->urisMatch($route['uri'], $requestUri)) {
                $allowedMethods[] = $route['method'];
            }
        }

        if (!empty($allowedMethods)) {
            throw new MethodNotAllowedException($allowedMethods);
        }

        throw new NotFoundException("Ruta no encontrada: $requestUri");
    }

    /**
     * Construye un patrón regex a partir de una URI con parámetros
     */
    protected function buildPatternFromUri($uri)
    {
        // Reemplazar parámetros con regex
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_-]*)\}/', '(?P<$1>[^/]+)', $uri);

        // Reemplazar parámetros opcionales
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_-]*)\?\}/', '(?P<$1>[^/]*)?', $pattern);

        // Escapar barras para regex
        $pattern = str_replace('/', '\/', $pattern);

        return '/^' . $pattern . '$/';
    }

    /**
     * Normaliza la URI
     */
    protected function normalizeUri($uri)
    {
        // Eliminar la barra inicial si existe
        $uri = '/' . ltrim($uri, '/');

        // Eliminar la barra final si no es la raíz
        if ($uri !== '/' && substr($uri, -1) === '/') {
            $uri = rtrim($uri, '/');
        }

        return $uri;
    }

    /**
     * Verifica si dos URIs coinciden (sin parámetros)
     */
    protected function urisMatch($routeUri, $requestUri)
    {
        $routePattern = $this->buildPatternFromUri($routeUri);
        return preg_match($routePattern, $requestUri);
    }

    /**
     * Ejecuta el handler de la ruta
     */
    protected function runHandler($handler, $params): mixed
    {
        // Array handler: [Controller::class, 'method']
        if (is_array($handler) && count($handler) === 2 && is_string($handler[0]) && is_string($handler[1])) {
            [$class, $method] = $handler;

            if (!class_exists($class)) {
                throw new NotFoundException("Controlador no encontrado: $class");
            }

            $instance = $this->container?->make($class) ?? new $class();
            $this->injectContainer($instance);

            if (!method_exists($instance, $method)) {
                throw new NotFoundException("Método $method no existe en $class");
            }

            return call_user_func_array([$instance, $method], array_values($params));
        }

        // Callable handler (closures, function names)
        if (is_callable($handler)) {
            return call_user_func_array($handler, array_values($params));
        }

        // String handler backward compatibility: 'Controller@method'
        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$class, $method] = explode('@', $handler);

            if (!class_exists($class)) {
                throw new NotFoundException("Controlador no encontrado: $class");
            }

            $instance = $this->container?->make($class) ?? new $class();
            $this->injectContainer($instance);

            if (!method_exists($instance, $method)) {
                throw new NotFoundException("Método $method no existe en $class");
            }

            return call_user_func_array([$instance, $method], array_values($params));
        }

        throw new \InvalidArgumentException("Handler no válido");
    }

    /**
     * Comparte el contenedor con los controladores para que el Controller base
     * pueda inyectar SiteSettings en las vistas (navbar, footer y favicon).
     */
    protected function injectContainer(mixed $instance): void
    {
        if ($instance instanceof Controller && $this->container !== null) {
            $instance->setContainer($this->container);
        }
    }


    /**
     * Ejecuta middleware
     */
    protected function runMiddleware($middlewareStack, array $requestContext): void
    {
        foreach ($middlewareStack as $middleware) {
            if (is_string($middleware)) {
                $middlewareClass = class_exists($middleware) ? $middleware : 'App\\Middleware\\' . $middleware;

                if (!class_exists($middlewareClass)) {
                    throw new \InvalidArgumentException("Middleware no encontrado: $middleware");
                }

                $middlewareInstance = $this->container?->make($middlewareClass) ?? new $middlewareClass();

                if (!method_exists($middlewareInstance, 'handle')) {
                    throw new \InvalidArgumentException("Middleware $middleware no tiene método handle");
                }

                $middlewareInstance->handle($requestContext);
            } elseif (is_callable($middleware)) {
                call_user_func_array($middleware, [$requestContext]);
            }
        }
    }

    /**
     * Genera URL a partir de nombre de ruta
     */
    public function route($name, $parameters = [])
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \InvalidArgumentException("Ruta con nombre '$name' no encontrada");
        }

        $uri = $this->namedRoutes[$name];

        // Reemplazar parámetros en la URI
        foreach ($parameters as $key => $value) {
            $uri = str_replace('{' . $key . '}', $value, $uri);
            $uri = str_replace('{' . $key . '?}', $value, $uri);
        }

        // Eliminar parámetros opcionales no proporcionados
        $uri = preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_-]*\?\}/', '', $uri);

        return $this->basePath . $uri;
    }

    /**
     * Obtiene la URI actual
     */
    protected function getCurrentUri()
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        // Eliminar query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // Eliminar base path si existe
        if ($this->basePath && strpos($uri, $this->basePath) === 0) {
            $uri = substr($uri, strlen($this->basePath));
        }

        return $uri;
    }

    /**
     * Obtiene el método HTTP de la solicitud.
     *
     * Solo se considera el método real de la solicitud. El header
     * X-HTTP-Method-Override no se soporta (hallazgo BAJO de la auditoría):
     * no era usado por ningún flujo del proyecto y reducía la superficie de
     * confusión de método/smuggling.
     */
    protected function getRequestMethod()
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Establece el base path
     */
    public function setBasePath($basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        return $this;
    }

    /**
     * Obtiene todas las rutas registradas
     */
    public function getRoutes()
    {
        return $this->routes;
    }
}
