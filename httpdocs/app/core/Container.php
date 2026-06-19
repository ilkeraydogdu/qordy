<?php
namespace App\Core;

class Container {
    private $bindings = [];
    private $instances = [];
    private $singletons = [];

    /**
     * Bind a class or interface to a concrete implementation
     * @param string $abstract The abstract class or interface
     * @param mixed $concrete The concrete implementation or closure
     * @param bool $singleton Whether to bind as a singleton
     */
    public function bind(string $abstract, $concrete = null, bool $singleton = false) {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => $singleton
        ];
    }

    /**
     * Bind a class as a singleton
     * @param string $abstract The abstract class or interface
     * @param mixed $concrete The concrete implementation or closure
     */
    public function singleton(string $abstract, $concrete = null) {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Resolve a class from the container
     * @param string $abstract The abstract class or interface
     * @return mixed The resolved instance
     */
    public function resolve(string $abstract) {
        // Check if it's already instantiated as a singleton
        if (isset($this->instances[$abstract]) && isset($this->bindings[$abstract]['singleton']) && $this->bindings[$abstract]['singleton']) {
            return $this->instances[$abstract];
        }

        // Check if binding exists
        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract]['concrete'];
            
            if ($concrete instanceof \Closure) {
                $instance = $concrete($this);
            } elseif (is_string($concrete)) {
                $instance = $this->build($concrete);
            } else {
                $instance = $concrete;
            }

            // Store singleton instance
            if (isset($this->bindings[$abstract]['singleton']) && $this->bindings[$abstract]['singleton']) {
                $this->instances[$abstract] = $instance;
            }

            return $instance;
        }

        // If no binding exists, try to instantiate directly
        return $this->build($abstract);
    }

    /**
     * Build an instance of a class
     * @param string $concrete The class to build
     * @return mixed The built instance
     */
    private function build(string $concrete) {
        // Check if class exists
        if (!class_exists($concrete)) {
            throw new \Exception("Class {$concrete} does not exist");
        }

        $reflector = new \ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new \Exception("Class {$concrete} is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete;
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveDependencies($parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve dependencies for a class
     * @param array $parameters Constructor parameters
     * @return array Resolved dependencies
     */
    private function resolveDependencies(array $parameters): array {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type === null) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new \Exception("Cannot resolve parameter {$parameter->getName()}");
                }
            } else {
                $typeName = $type->getName();
                
                // Check if it's a built-in type
                if ($type->isBuiltin()) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                    } else {
                        throw new \Exception("Cannot resolve built-in type {$typeName}");
                    }
                } else {
                    // Try to resolve from container
                    $dependencies[] = $this->resolve($typeName);
                }
            }
        }

        return $dependencies;
    }

    /**
     * Check if a binding exists
     * @param string $abstract The abstract class or interface
     * @return bool Whether the binding exists
     */
    public function has(string $abstract): bool {
        return isset($this->bindings[$abstract]) || class_exists($abstract);
    }

    /**
     * Get all bindings
     * @return array All bindings
     */
    public function getBindings(): array {
        return $this->bindings;
    }

    /**
     * Get all singleton instances
     * @return array All singleton instances
     */
    public function getInstances(): array {
        return $this->instances;
    }

    /**
     * Call a method on a class with dependency injection
     * @param mixed $callback The callback to call
     * @param array $parameters Additional parameters
     * @return mixed The result of the callback
     */
    public function call($callback, array $parameters = []) {
        if (is_string($callback) && strpos($callback, '@') !== false) {
            list($class, $method) = explode('@', $callback);
            $instance = $this->resolve($class);
            $callback = [$instance, $method];
        } elseif (is_string($callback) && class_exists($callback)) {
            $instance = $this->resolve($callback);
            $callback = [$instance, '__invoke'];
        }

        if ($callback instanceof \Closure) {
            $reflector = new \ReflectionFunction($callback);
        } else {
            $reflector = new \ReflectionMethod($callback[0], $callback[1]);
        }
        $reflectionParams = $reflector->getParameters();

        $dependencies = [];
        foreach ($reflectionParams as $index => $parameter) {
            $type = $parameter->getType();

            if ($type !== null && !$type->isBuiltin()) {
                $typeName = $type->getName();
                $dependencies[] = $this->resolve($typeName);
            } elseif (isset($parameters[$index])) {
                $dependencies[] = $parameters[$index];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                $dependencies[] = null;
            }
        }

        return call_user_func_array($callback, $dependencies);
    }
}