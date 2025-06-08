<?php

/**
 * Holds parameter resolver class that validates dependencies via Attributes-PHP/validation library
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints\DI;

use Attributes\Options\Exceptions\InvalidOptionException;
use Attributes\Validation\Exceptions\ValidationException;
use Attributes\Validation\Validatable;
use Attributes\Validation\Validator;
use Invoker\ParameterResolver\ParameterResolver;
use ReflectionException;
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
    private Validatable $validator;

    public function __construct(?Validatable $validator = null)
    {
        $this->validator = $validator ?? new Validator;
    }

    /**
     * @return array - The resolved parameters
     *
     * @throws ValidationException - If any of the provided parameters are invalid
     * @throws InvalidOptionException - If an invalid alias generator is provided
     * @throws ReflectionException
     */
    public function getParameters(
        ReflectionFunctionAbstract $reflection,
        array $providedParameters,
        array $resolvedParameters
    ): array {
        $request = $providedParameters['request'];
        $allParameters = new LazyLoadParameters(reflection: $reflection, request: $request, resolvedData: $resolvedParameters);
        $allParameters = $this->validator->validateCallable($allParameters, $reflection->getClosure());

        return $this->convertToNumericArgs($allParameters, $reflection);
    }

    protected function convertToNumericArgs(array $allParameters, ReflectionFunctionAbstract $reflection): array
    {
        $numericArgs = [];
        foreach ($reflection->getParameters() as $index => $parameter) {
            $numericArgs[$index] = $allParameters[$parameter->getName()] ?? $parameter->getDefaultValue();
        }

        return $numericArgs;
    }
}
