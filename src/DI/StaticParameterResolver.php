<?php

/**
 * Holds parameter resolver that maps WordPress resources and rest that removes repeating http status in $data.
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints\DI;

use Attributes\Wp\FastEndpoints\Endpoint;
use Invoker\ParameterResolver\ParameterResolver;
use ReflectionFunctionAbstract;
use ReflectionNamedType;
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

        foreach ($parameters as $index => $parameter) {
            if (! $parameter->hasType()) {
                continue;
            }

            $type = $parameter->getType();
            if (! ($type instanceof ReflectionNamedType) || $type->isBuiltin()) {
                continue;
            }

            $typeName = $type->getName();
            switch ($typeName) {
                case WP_REST_Request::class:
                    $resolvedParameters[$index] = $providedParameters['request'];
                    break;
                case WP_REST_Response::class:
                    $resolvedParameters[$index] = $providedParameters['response'];
                    break;
                case Endpoint::class:
                    $resolvedParameters[$index] = $providedParameters['endpoint'];
                    break;
            }
        }

        return $resolvedParameters;
    }
}
