<?php

declare(strict_types=1);

namespace GPDCore\Graphql;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionMethod;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Doctrine\Definition\EntityID;

/**
 * A field resolver that will allow access to public properties and getter.
 * Arguments, if any, will be forwarded as is to the method.
 */
final class DefaultDoctrineFieldResolver
{
    /**
     * @param mixed $source
     * @param mixed[] $args
     * @param mixed $context
     * @param ResolveInfo $info
     *
     * @return null|mixed
     */
    public function __invoke($source, array $args, $context, ResolveInfo $info)
    {
        /** @var string $fieldName */
        $fieldName = $info->fieldName;
        $property = null;

        if (is_object($source)) {
            $property = $this->resolveGQL($source, $args, $context, $info);
            if ($property === null) {
                $property = $this->resolveObject($source, $args, $fieldName);
            }
        } elseif (is_array($source)) {
            $property = $this->resolveArray($source, $fieldName);
        }

        return $property instanceof Closure ? $property($source, $args, $context) : $property;
    }


    private function resolveGQL($source, array $args, $context, ResolveInfo $info)
    {
        $fieldName = $info->fieldName;
        $resolver = $this->getResolver($source, $fieldName);
        if ($resolver) {
            $resolveClass = $this->getResolveClass($source);
            $resolveObj = new $resolveClass;
            $args = [
                $source, $args, $context, $info
            ];
            return $resolver->invoke($resolveObj, ...$args);
        } else {
            return null;
        }
    }

    /**
     * Resolve for an object
     *
     * @param mixed $source
     * @param array $args
     * @param string $fieldName
     *
     * @return mixed
     */
    private function resolveObject($source, array $args, string $fieldName)
    {



        $getter = $this->getGetter($source, $fieldName);
        if ($getter) {
            $args = $this->orderArguments($getter, $args);

            return $getter->invoke($source, ...$args);
        }

        if (isset($source->{$fieldName})) {
            return $source->{$fieldName};
        }

        return null;
    }

    /**
     * Resolve for an array
     *
     * @param mixed $source
     * @param string $fieldName
     *
     * @return mixed
     */
    private function resolveArray($source, string $fieldName)
    {
        return $source[$fieldName] ?? null;
    }

    /**
     * Return the getter/isser method if any valid one exists
     *
     * @param mixed $source
     * @param string $name
     *
     * @return null|ReflectionMethod
     */
    private function getGetter($source, string $name): ?ReflectionMethod
    {
        if (!preg_match('~^(is|has)[A-Z]~', $name)) {
            $name = 'get' . ucfirst($name);
        }

        $class = new ReflectionClass($source);
        if ($class->hasMethod($name)) {
            $method = $class->getMethod($name);
            if ($method->getModifiers() & ReflectionMethod::IS_PUBLIC) {
                return $method;
            }
        }

        return null;
    }
    /**
     * Return the resolve  method of GQLResolve class if any valid one exists
     *
     * @param mixed $source
     * @param string $name
     *
     * @return null|ReflectionMethod
     */
    private function getResolver($source, string $name): ?ReflectionMethod
    {
        $resolveClass = $this->getResolveClass($source);
        if ($resolveClass === null) {
            return null;
        }
        if (!preg_match('~^(is|has)[A-Z]~', $name)) {
            $name = 'resolve' . ucfirst($name);
        }
        try {
            $class = new ReflectionClass($resolveClass);
            if ($class->hasMethod($name)) {
                $method = $class->getMethod($name);
                if ($method->getModifiers() & ReflectionMethod::IS_PUBLIC) {
                    return $method;
                }
            }
        } catch (Exception $e) {
            return null;
        }


        return null;
    }

    private function getResolveClass($source)
    {
        $className = is_object($source) ? get_class($source) : $source;
        $className = str_replace('DoctrineProxies\__CG__','', $className);
        if (!is_string($className)) {
            return null;
        }
        $resolveClass = sprintf("%sGQLResolve", $className);
        return $resolveClass;
    }

    /**
     * Re-order associative args to ordered args
     *
     * @param ReflectionMethod $method
     * @param array $args
     *
     * @return array
     */
    private function orderArguments(ReflectionMethod $method, array $args): array
    {
        $result = [];
        if (!$args) {
            return $result;
        }

        foreach ($method->getParameters() as $param) {
            if (array_key_exists($param->getName(), $args)) {
                $arg = $args[$param->getName()];

                // Fetch entity from DB
                if ($arg instanceof EntityID) {
                    $arg = $arg->getEntity();
                }

                $result[] = $arg;
            }
        }

        return $result;
    }
}
