<?php

/**
 * Holds parameter resolver that maps WordPress resources and rest that removes repeating http status in $data.
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints\DI;

use Attributes\Serialization\Serializable;
use Attributes\Serialization\Serializer;
use Attributes\Validation\Validatable;
use Attributes\Validation\Validator;
use Attributes\Wp\FastEndpoints\Options;
use Invoker\Exception\InvocationException;
use Invoker\ParameterResolver\ParameterResolver;
use ReflectionFunctionAbstract;

/**
 * Maps any request, response and endpoint.
 *
 * Parameters that are not indexed by a string are ignored.
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
class InjectParameterResolver implements ParameterResolver
{
    protected array $injectables = [];

    protected array $resolvedInjectables = [];

    protected array $callStack = [];

    protected Validatable $validator;

    protected Serializable $serializer;

    public function __construct(?Validatable $validator = null, ?Serializable $serializer = null)
    {
        $this->validator = $validator ?? new Validator;
        $this->serializer = $serializer ?? new Serializer;
        $this->injectables['validator'] = $this->validator;
        $this->injectables['serializer'] = $this->serializer;
    }

    /**
     * @throws InvocationException
     */
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

        $endpoint = $providedParameters['endpoint'];
        foreach ($parameters as $index => $parameter) {
            $allInjectAttributes = $parameter->getAttributes(Options\Inject::class);
            if (empty($allInjectAttributes)) {
                continue;
            }

            $instance = $allInjectAttributes[0]->newInstance();
            $injectableName = $instance->getName() ?: $parameter->getName();
            if (isset($this->resolvedInjectables[$injectableName])) {
                $resolvedParameters[$index] = $this->resolvedInjectables[$injectableName];

                continue;
            }

            if (! isset($this->injectables[$injectableName]) && ! is_callable($injectableName)) {
                $route = $endpoint->getFullRoute();
                $name = $parameter->getName();
                throw new InvocationException("Injectable not found for parameter $name in route $route");
            }

            $injectable = $this->injectables[$injectableName] ?? $injectableName;
            if (! is_callable($injectable)) {
                $this->resolvedInjectables[$injectableName] = $injectable;
                $resolvedParameters[$index] = $injectable;

                continue;
            }

            if (isset($this->callStack[$injectableName])) {
                $route = $endpoint->getFullRoute();
                throw new InvocationException("Infinite injectables loop in route $route");
            }

            $invoker = $endpoint->getInvoker();
            $allParameters = array_merge($providedParameters, $resolvedParameters);
            $this->callStack[$injectableName] = true;
            $result = $invoker->call($injectable, $allParameters);
            unset($this->callStack[$injectableName]);
            $this->resolvedInjectables[$injectableName] = $result;
            $resolvedParameters[$index] = $result;
        }

        return $resolvedParameters;
    }

    public function setInjectables(array $injectables): void
    {
        $this->injectables = array_merge($this->injectables, $injectables);
    }
}
