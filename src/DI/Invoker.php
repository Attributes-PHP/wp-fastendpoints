<?php

/**
 * Holds a dependency injection invoker that resolves dependencies per each endpoint and middleware
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints\DI;

use Attributes\Serialization\Serializer;
use Attributes\Validation\Validator;
use Invoker\Invoker as BaseInvoker;
use Invoker\ParameterResolver\ParameterResolver;
use Invoker\ParameterResolver\ResolverChain;
use Psr\Container\ContainerInterface;

/**
 * Dependency injection invoker.
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
class Invoker extends BaseInvoker
{
    protected InjectParameterResolver $injectableParameterResolver;

    public function __construct(?ParameterResolver $parameterResolver = null, ?ContainerInterface $container = null)
    {
        $parameterResolver = $parameterResolver ?: $this->createParameterResolver();
        parent::__construct($parameterResolver, $container);
    }

    private function createParameterResolver(): ParameterResolver
    {
        $validator = apply_filters('fastendpoints_validator', new Validator);
        $serializer = apply_filters('fastendpoints_serializer', new Serializer);
        $this->injectableParameterResolver = new InjectParameterResolver(validator: $validator, serializer: $serializer);

        return new ResolverChain([
            new StaticParameterResolver,
            $this->injectableParameterResolver,
            new ValidationParameterResolver(validator: $validator),
        ]);
    }

    public function setInjectables(array $injectables): void
    {
        $this->injectableParameterResolver->setInjectables($injectables);
    }
}
