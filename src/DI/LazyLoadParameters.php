<?php

/**
 * Holds logic to lazy load function parameters needed for the Attributes\Validation\Validator->validateCallable
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints\DI;

use ArrayObject;
use Attributes\Wp\FastEndpoints\Options\From;
use ReflectionAttribute;
use ReflectionFunctionAbstract;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use WP_REST_Request;

/**
 * Class to lazy load function parameters for Attributes\Validation\Validator->validateCallable.
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
class LazyLoadParameters extends ArrayObject
{
    protected ReflectionFunctionAbstract $reflection;

    protected WP_REST_Request $request;

    protected ?int $currentIndex = null;

    protected array $fromParams = [];

    public function __construct(ReflectionFunctionAbstract $reflection, WP_REST_Request $request, array $resolvedData)
    {
        $this->reflection = $reflection;
        $this->request = $request;
        parent::__construct($resolvedData);
    }

    public function offsetExists($key): bool
    {
        if (is_numeric($key)) {
            $this->currentIndex = (int) $key;

            return parent::offsetExists($key);
        }

        return $this->find($key);
    }

    public function offsetGet($key): mixed
    {
        if (is_numeric($key)) {
            $this->currentIndex = (int) $key;

            return parent::offsetGet($key);
        }

        $this->find($key);

        return parent::offsetGet($key);
    }

    /**
     * Looks up for a given parameter by name/alias according to the type-hint.
     * If found, stores it via parent::offsetSet.
     *
     * @param  $key  - the parameter name to look for
     * @return bool - true if found or false otherwise
     */
    protected function find($key): bool
    {
        if (parent::offsetExists($key)) {
            return true;
        }

        $allParameters = $this->reflection->getParameters();
        $parameter = $allParameters[$this->currentIndex];
        $fromParams = $this->getFromParams($parameter);

        if ($this->isToFindParamByKey($parameter)) {
            return $this->findParamByKey($fromParams, $key, $parameter->getType());
        }

        $parameterType = $parameter->getType();
        if (! ($parameterType instanceof ReflectionUnionType)) {
            return $this->findParam($fromParams, $key);
        }

        foreach ($parameterType->getTypes() as $type) {
            if ($type->isBuiltin()) {
                if (! $this->findParamByKey($fromParams, $key, $type)) {
                    continue;
                }

                return true;
            }

            $this->findParam($fromParams, $key);
        }

        return parent::offsetExists($key);
    }

    /**
     * Looks up for a parameter by key. If found, saves it via parent::offsetSet
     *
     * @param  array|null  $fromParams  - The parameters the user wants we rely on, if set, otherwise null is provided
     * @param  mixed  $key  - The key to look for
     * @param  ?ReflectionNamedType  $type  - The property type-hint in question, or null if none.
     * @return bool - true if found or false otherwise
     */
    protected function findParamByKey(?array $fromParams, mixed $key, ?ReflectionNamedType $type): bool
    {
        $allParams = $fromParams ?? $this->getUrlOrQueryParams($key);
        if (! array_key_exists($key, $allParams)) {
            return false;
        }

        $value = $this->parseBuiltinParamValue($allParams[$key], $type);
        parent::offsetSet($key, $value);

        return true;
    }

    /**
     * Looks up for a parameter. If found it stores it via parent::offsetSet
     *
     * @param  array|null  $fromParams  - The parameters the user wants we rely on, if set, otherwise null is provided
     * @param  mixed  $key  - The key to look for
     * @return bool - true if found or false otherwise
     */
    protected function findParam(?array $fromParams, mixed $key): bool
    {
        $allParams = $fromParams ?? $this->getJsonOrBodyParams();
        parent::offsetSet($key, $allParams);

        return true;
    }

    /**
     * Parses a built-in value
     *
     * @param  mixed  $value  - The value to be parsed
     * @param  ?ReflectionNamedType  $type  - The property type-hint in question, or null if none.
     */
    protected function parseBuiltinParamValue(mixed $value, ?ReflectionNamedType $type): mixed
    {
        if (! $type || ! $type->isBuiltin()) {
            return $value;
        }

        $name = $type->getName();
        if ($name !== 'array' && $name !== 'object') {
            return $value;
        }

        if (is_array($value) || is_object($value)) {
            return $value;
        }

        $value = (string) $value;

        return explode(',', $value);
    }

    /**
     * Retrieves the parameters that the user wants to use for the request
     *
     * @return array|null - A list of parameters to be used or null if nothing specified
     */
    protected function getFromParams(ReflectionParameter $parameter): ?array
    {
        $allFrom = $parameter->getAttributes(From::class, ReflectionAttribute::IS_INSTANCEOF);
        if (! $allFrom) {
            return null;
        }

        $allParams = [];
        foreach ($allFrom as $from) {
            $fromParams = $this->fromParams[$from->getName()] ?? $from->newInstance()->getParams($this->request);
            $allParams = array_merge($fromParams, $allParams);
        }

        return $allParams;
    }

    protected function isToFindParamByKey(ReflectionParameter $parameter): bool
    {
        return ! $parameter->hasType() || ($parameter->getType() instanceof ReflectionNamedType && $parameter->getType()->isBuiltin());
    }

    protected function getUrlOrQueryParams(mixed $key): array
    {
        $urlParams = $this->request->get_url_params();
        if (array_key_exists($key, $urlParams)) {
            return $urlParams;
        }
        $queryParams = $this->request->get_query_params();
        if (array_key_exists($key, $queryParams)) {
            return $queryParams;
        }

        return $urlParams + $queryParams;
    }

    protected function getJsonOrBodyParams(): array
    {
        $allParams = $this->request->is_json_content_type() ? $this->request->get_json_params() : $this->request->get_body_params();

        return $allParams ?: [];
    }
}
