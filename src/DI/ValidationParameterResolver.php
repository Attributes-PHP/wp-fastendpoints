<?php

/**
 * Holds parameter resolver class that validates dependencies via Attributes-PHP/validation library
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints\DI;

use Attributes\Validation\Exceptions\ValidationException;
use Attributes\Validation\Validator;
use Invoker\ParameterResolver\ParameterResolver;
use ReflectionFunctionAbstract;

/**
 * Relies on the Attributes-PHP/validation to validate a given parameters and transform them into
 * the expected type hints.
 *
 * E.g. `$callable = function (int $foo) {};
 *       $invoker->call($callable, ['foo' => '2'])` will inject 2
 *       in the parameter named `$foo`.
 *
 * Parameters that are not indexed by a string are ignored.
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
class ValidationParameterResolver implements ParameterResolver
{
    /**
     * @return array - The resolved parameters
     *
     * @throws ValidationException - If any of the provided parameters are invalid
     */
    public function getParameters(
        ReflectionFunctionAbstract $reflection,
        array $providedParameters,
        array $resolvedParameters
    ): array {
        $allParameters = array_merge($providedParameters, $resolvedParameters);
        $validator = new Validator;
        $validator = apply_filters('fastendpoints_validation_schema', $validator);

        return $validator->validateCallable($allParameters, $reflection->getName());
    }
}
