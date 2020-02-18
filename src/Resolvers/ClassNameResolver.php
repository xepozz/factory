<?php

namespace Yiisoft\Factory\Resolvers;

use Yiisoft\Factory\Definitions\ClassDefinition;
use Yiisoft\Factory\Definitions\DefinitionInterface;
use Yiisoft\Factory\Definitions\InvalidDefinition;
use Yiisoft\Factory\Definitions\ValueDefinition;
use Yiisoft\Factory\Exceptions\NotInstantiableException;

/**
 * Class ClassNameResolver
 * This implementation resolves dependencies by using class type hints.
 * Note that service names need not match the parameter names, parameter names are ignored
 */
class ClassNameResolver implements DependencyResolverInterface
{
    /**
     * @inheritdoc
     */
    public function resolveConstructor(string $class): array
    {
        $reflectionClass = new \ReflectionClass($class);
        if (!$reflectionClass->isInstantiable()) {
            throw new NotInstantiableException($class);
        }
        $constructor = $reflectionClass->getConstructor();
        return $constructor === null ? [] : $this->resolveFunction($constructor);
    }

    private function resolveFunction(\ReflectionFunctionAbstract $reflectionFunction): array
    {
        $result = [];
        foreach ($reflectionFunction->getParameters() as $parameter) {
            $result[] = $this->resolveParameter($parameter, $reflectionFunction);
        }
        return $result;
    }

    private function resolveParameter(\ReflectionParameter $parameter, \ReflectionFunctionAbstract $function): DefinitionInterface
    {
        $type = $parameter->getType();

        if (($type !== null && $type->allowsNull()) || $function->isInternal()) {
            return new ValueDefinition(
                $parameter->isDefaultValueAvailable()
                    ? $parameter->getDefaultValue()
                    : null
            );
        }

        if ($parameter->isOptional()) {
            return new ValueDefinition($parameter->getDefaultValue());
        }

        if ($type !== null && !$type->isBuiltin()) {
            return new ClassDefinition($type->getName(), $type->allowsNull());
        }

        return new InvalidDefinition();
    }

    /**
     * @inheritdoc
     */
    public function resolveCallable(callable $callable): array
    {
        return $this->resolveFunction(new \ReflectionFunction(\Closure::fromCallable($callable)));
    }
}
