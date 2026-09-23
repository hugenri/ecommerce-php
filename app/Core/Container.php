<?php

namespace App\Core;

use Closure;
use ReflectionClass;
use App\Core\Exceptions\ContainerException;
use App\Core\Exceptions\BindingResolutionException;
use App\Core\Exceptions\CircularDependencyException;
use App\Core\Exceptions\NotInstantiableException;

class Container
{
    protected $bindings = [];

    protected $instances = [];

    protected $singletons = [];

    protected $resolving = [];

    protected $reflectionCache = [];

    public function bind($abstract, $concrete = null)
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = $concrete;
    }

    public function singleton($abstract, $concrete = null)
    {
        $this->singletons[$abstract] = true;
        $this->bind($abstract, $concrete);
    }

    public function instance($abstract, $instance)
    {
        $this->instances[$abstract] = $instance;
    }

    public function make($abstract)
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (in_array($abstract, $this->resolving, true)) {
            $chain = implode(' → ', $this->resolving) . ' → ' . $abstract;
            throw new CircularDependencyException(
                "Dependencia circular detectada: {$chain}"
            );
        }

        $this->resolving[] = $abstract;

        try {
            if (isset($this->bindings[$abstract])) {
                $concrete = $this->bindings[$abstract];

                if ($concrete instanceof Closure) {
                    $instance = $concrete($this);
                } else {
                    $instance = $this->build($concrete);
                }
            } else {
                $instance = $this->build($abstract);
            }

            if (isset($this->singletons[$abstract])) {
                $this->instances[$abstract] = $instance;
            }

            array_pop($this->resolving);

            return $instance;
        } catch (ContainerException $e) {
            array_pop($this->resolving);
            throw $e;
        } catch (\Exception $e) {
            array_pop($this->resolving);
            throw new BindingResolutionException(
                "Error al resolver [{$abstract}]: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function has($abstract)
    {
        return isset($this->bindings[$abstract])
            || isset($this->instances[$abstract]);
    }

    protected function build($class)
    {
        $reflector = $this->resolveReflector($class);

        if (!$reflector->isInstantiable()) {
            if ($reflector->isInterface()) {
                throw new BindingResolutionException(
                    "No hay binding registrado para la interfaz {$class}"
                );
            }

            if ($reflector->isAbstract()) {
                throw new NotInstantiableException(
                    "No se puede instanciar la clase abstracta {$class}"
                );
            }

            throw new NotInstantiableException(
                "No se puede instanciar {$class}: no es instanciable"
            );
        }

        $constructor = $reflector->getConstructor();

        if (!$constructor) {
            return new $class();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            if ($type && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
            } elseif ($type && $type->isBuiltin()) {
                throw new BindingResolutionException(
                    "No se puede resolver el parámetro \${$param->getName()} de tipo {$type->getName()} en {$class}: "
                    . "no tiene valor por defecto. Define un valor por defecto o registra el parámetro en el contenedor."
                );
            } else {
                $dependencies[] = null;
            }
        }

        return $reflector->newInstanceArgs($dependencies);
    }

    protected function resolveReflector($class)
    {
        if (!isset($this->reflectionCache[$class])) {
            $this->reflectionCache[$class] = new ReflectionClass($class);
        }

        return $this->reflectionCache[$class];
    }

}
