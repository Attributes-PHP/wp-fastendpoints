<?php

/**
 * Holds parameter resolver that maps WordPress resources and rest that removes repeating http status in $data.
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints\DI;

use Attributes\Options;
use Attributes\Wp\FastEndpoints\Endpoint;
use Invoker\ParameterResolver\ParameterResolver;
use ReflectionFunctionAbstract;
use ReflectionNamedType;
use ReflectionParameter;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Maps any request, response and endpoint.
 *
 * Parameters that are not indexed by a string are ignored.
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
class StaticParameterResolver implements ParameterResolver
{
    public function getParameters(
        ReflectionFunctionAbstract $reflection,
        array $providedParameters,
        array $resolvedParameters
    ): array {
        $parameters = $reflection->getParameters();

        // Skip parameters already resolved
        if (! empty($resolvedParameters)) {
            $parameters = array_diff_key($parameters, $resolvedParameters);
        }

        $defaultAliasGenerator = $this->getDefaultAliasGenerator($reflection);
        foreach ($parameters as $index => $parameter) {
            $name = $this->getParamName($parameter, $defaultAliasGenerator);

            if ($name === 'request' || $name === 'response' || $name === 'endpoint') {
                $resolvedParameters[$index] = $providedParameters[$name];

                continue;
            }

            if (! $parameter->hasType()) {
                continue;
            }

            $type = $parameter->getType();
            if (! ($type instanceof ReflectionNamedType)) {
                continue;
            }

            $typeName = $type->getName();
            if ($typeName instanceof WP_REST_Request) {
                $resolvedParameters[$index] = $providedParameters['request'];

                continue;
            }

            if ($typeName instanceof WP_REST_Response) {
                $resolvedParameters[$index] = $providedParameters['response'];

                continue;
            }

            if ($typeName instanceof Endpoint) {
                $resolvedParameters[$index] = $providedParameters['endpoint'];
            }
        }

        return $resolvedParameters;
    }

    private function getParamName(ReflectionParameter $parameter, callable $defaultAliasGenerator): string
    {
        $name = $parameter->name;
        $allAliasAttributes = $parameter->getAttributes(Options\Alias::class);
        foreach ($allAliasAttributes as $attribute) {
            $instance = $attribute->newInstance();

            return $instance->getAlias($name);
        }

        return $defaultAliasGenerator($name);
    }

    /**
     * Retrieves the default alias generator for a given class
     */
    protected function getDefaultAliasGenerator(ReflectionFunctionAbstract $reflection): callable
    {
        $allAttributes = $reflection->getAttributes(Options\AliasGenerator::class);
        foreach ($allAttributes as $attribute) {
            $instance = $attribute->newInstance();

            return $instance->getAliasGenerator();
        }

        return fn (string $name) => $name;
    }
}
