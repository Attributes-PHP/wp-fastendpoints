<?php

/**
 * Holds logic for registering custom REST endpoints
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints;

use Attributes\Validation\Exceptions\ValidationException;
use Attributes\Wp\FastEndpoints\Contracts\Http\Endpoint as EndpointInterface;
use Attributes\Wp\FastEndpoints\Contracts\Middlewares\Middleware;
use Attributes\Wp\FastEndpoints\Helpers\WpError;
use Attributes\Wp\FastEndpoints\Middlewares\ResponseMiddleware;
use Exception;
use Invoker\Exception\InvocationException;
use Invoker\Exception\NotCallableException;
use Invoker\Exception\NotEnoughParametersException;
use WP_Error;
use WP_Http;
use WP_REST_Request;
use WP_REST_Response;

use function apply_filters;
use function array_merge;
use function current_user_can;
use function esc_html__;
use function is_string;
use function is_wp_error;
use function register_rest_route;
use function str_ends_with;
use function str_starts_with;
use function trim;
use function wp_die;

/**
 * REST Endpoint that registers custom WordPress REST endpoint using register_rest_route
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
class Endpoint implements EndpointInterface
{
    /**
     * HTTP endpoint method - also supports values from WP_REST_Server (e.g. WP_REST_Server::READABLE)
     */
    protected string $method;

    /**
     * HTTP route
     */
    protected string $route;

    /**
     * HTTP route with router namespace and version
     */
    protected string $fullRoute;

    /**
     * Same as the register_rest_route $args parameter
     */
    protected array $args = [];

    /**
     * Main endpoint handler
     *
     * @var callable
     */
    protected $handler;

    /**
     * Plugins needed for the REST route
     *
     * @var ?array<string>
     */
    protected ?array $plugins = null;

    /**
     * Same as the register_rest_route $override parameter
     */
    protected bool $override;

    /**
     * Set of functions used inside the permissionCallback endpoint
     *
     * @var array<callable>
     */
    protected array $permissionHandlers = [];

    /**
     * Set of functions used to be called before handling a request e.g. schema validation
     *
     * @var array<callable>
     */
    protected array $onRequestHandlers = [];

    /**
     * Set of functions used to be called before sending a response to the client
     *
     * @var array<callable>
     */
    protected array $onResponseHandlers = [];

    /**
     * Dependency injection
     */
    protected ?DI\Invoker $invoker = null;

    /**
     * Creates a new instance of Endpoint
     *
     * @param  string  $method  POST, GET, PUT or DELETE or a value from WP_REST_Server (e.g. WP_REST_Server::EDITABLE).
     * @param  string  $route  Endpoint route.
     * @param  callable  $handler  User specified handler for the endpoint.
     * @param  array  $args  Same as the WordPress register_rest_route $args parameter. If set it can override the default
     *                       WP FastEndpoints arguments.
     * @param  bool  $override  Same as the WordPress register_rest_route $override parameter. Default value: false.
     */
    public function __construct(string $method, string $route, callable $handler, array $args = [], bool $override = false)
    {
        $this->method = $method;
        $this->route = $route;
        $this->fullRoute = $route;
        $this->handler = $handler;
        $this->args = $args;
        $this->override = $override;
    }

    /**
     * Registers the current endpoint using register_rest_route function.
     *
     * NOTE: Expects to be called inside the 'rest_api_init' WordPress action
     *
     * @internal
     *
     * @param  string  $namespace  WordPress REST namespace.
     * @param  string  $restBase  Endpoint REST base.
     * @return true|false true if successfully registered a REST route or false otherwise.
     */
    public function register(string $namespace, string $restBase): bool
    {
        $args = [
            'methods' => $this->method,
            'callback' => [$this, 'callback'],
            'permission_callback' => $this->permissionHandlers ? [$this, 'permissionCallback'] : '__return_true',
            'depends' => $this->plugins,
        ];

        // Override default arguments.
        $args = array_merge($args, $this->args);
        $args = apply_filters('fastendpoints_endpoint_args', $args, $namespace, $restBase, $this);

        // Skip registration if no args specified.
        if (! $args) {
            return false;
        }
        $route = $this->getRoute($restBase);
        $this->fullRoute = "/$namespace/$route";
        register_rest_route($namespace, $route, $args, $this->override);

        return true;
    }

    /**
     * Checks if the current user has the given WP capabilities. Example usage:
     *
     *      hasCap('edit_posts');
     *      hasCap('edit_post', $post->ID);
     *      hasCap('edit_post', '{post_id}');  // Replaces {post_id} with request parameter named post_id
     *      hasCap('edit_post_meta', $post->ID, $meta_key);
     *
     * @param  string  $capability  WordPress user capability to be checked against
     * @param  array  $args  Optional parameters, typically the object ID. You can also pass a future request parameter
     *                       via curly braces e.g. {post_id}
     */
    public function hasCap(string $capability, ...$args): self
    {
        if (! $capability) {
            wp_die(esc_html__('Invalid capability. Empty capability given'));
        }

        return $this->permission(function (WP_REST_Request $request) use ($capability, $args): bool|WpError {
            foreach ($args as &$arg) {
                if (! is_string($arg)) {
                    continue;
                }

                $arg = $this->replaceSpecialValue($request, $arg);
            }

            if (! current_user_can($capability, ...$args)) {
                return new WpError(WP_Http::FORBIDDEN, 'Not enough permissions');
            }

            return true;
        });
    }

    /**
     * /**
     * Adds a response schema to the endpoint. This JSON schema will later on filter the response before sending
     * it to the client. This can be great to:
     * 1) Discard unnecessary properties in the response to avoid the leakage of sensitive data and
     * 2) Making sure that the required data is retrieved.
     *
     * @param  string|object  $schema  Class name or instance.
     *
     * @throws Exception
     */
    public function returns(string|object $schema): self
    {
        $responseSchema = new ResponseMiddleware($schema);
        $this->onResponseHandlers[] = $responseSchema->onResponse(...);

        return $this;
    }

    /**
     * Registers a middleware
     *
     * @param  Middleware  $middleware  Middleware to be plugged.
     */
    public function middleware(Middleware $middleware): self
    {
        if (method_exists($middleware, 'onRequest')) {
            $this->onRequestHandlers[] = $middleware->onRequest(...);
        }
        if (method_exists($middleware, 'onResponse')) {
            $this->onResponseHandlers[] = $middleware->onResponse(...);
        }

        return $this;
    }

    /**
     * Specifies a set of plugins that are needed by the endpoint
     */
    public function depends(string|array $plugins): self
    {
        if (is_string($plugins)) {
            $plugins = [$plugins];
        }

        $this->plugins = array_merge($this->plugins ?: [], $plugins);

        return $this;
    }

    /**
     * Registers a permission callback
     *
     * @param  callable  $permissionCb  Method to be called to check current user permissions.
     */
    public function permission(callable $permissionCb): self
    {
        $this->permissionHandlers[] = $permissionCb;

        return $this;
    }

    /**
     * WordPress function callback to handle this endpoint
     *
     * @internal
     *
     * @throws NotCallableException
     * @throws NotEnoughParametersException
     * @throws InvocationException
     */
    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $dependencies = [
            'endpoint' => $this,
            'request' => $request,
            'response' => new WP_REST_Response,
        ];
        // onRequest handlers.
        $result = $this->runHandlers($this->onRequestHandlers, $dependencies);
        if (is_wp_error($result) || $result instanceof WP_REST_Response) {
            return $result;
        }

        // Main endpoint handler.
        $result = $this->runHandlers([$this->handler], $dependencies, isToReturnResult: true);
        if (is_wp_error($result) || $result instanceof WP_REST_Response) {
            return $result;
        }
        $dependencies['response']->set_data($result);

        // onResponse handlers.
        $result = $this->runHandlers($this->onResponseHandlers, $dependencies);
        if (is_wp_error($result) || $result instanceof WP_REST_Response) {
            return $result;
        }

        return $dependencies['response'];
    }

    /**
     * WordPress function callback to check permissions for this endpoint
     *
     * @internal
     *
     * @param  WP_REST_Request  $request  Current REST request.
     * @return bool|WP_Error true on success or WP_Error on error
     */
    public function permissionCallback(WP_REST_Request $request): bool|WP_Error
    {
        $dependencies = [
            'endpoint' => $this,
            'request' => $request,
        ];
        $result = $this->runHandlers($this->permissionHandlers, $dependencies);
        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Retrieves the current endpoint route
     *
     * @param  string  $restBase  REST base route.
     */
    protected function getRoute(string $restBase): string
    {
        $route = $restBase;
        if (! str_ends_with($restBase, '/') && ! str_starts_with($this->route, '/')) {
            $route .= '/';
        }
        $route .= $this->route;

        return apply_filters('fastendpoints_endpoint_route', $route, $this);
    }

    /**
     * Replaces specials values, like: {jobId} by $req->get_param('jobId')
     *
     * @param  WP_REST_Request  $request  Current REST request.
     * @param  string  $value  Value to be checked.
     * @return mixed The $value variable with all special parameters replaced.
     */
    protected function replaceSpecialValue(WP_REST_Request $request, string $value): mixed
    {
        // Checks if value matches a special value.
        // If so, replaces with request variable.
        $newValue = trim($value);
        if (! str_starts_with($newValue, '<') || ! str_ends_with($newValue, '>')) {
            return $value;
        }

        $newValue = substr($newValue, 1, -1);
        if (! $request->has_param($newValue)) {
            return $value;
        }

        return $request->get_param($newValue);
    }

    /**
     * Calls each handler.
     *
     * @param  array<callable>  $allHandlers  Holds all callables that we wish to run.
     * @param  array  $dependencies  Arguments to be passed in handlers.
     * @param  bool  $isToReturnResult  If set always returns the result of the handlers.
     * @return mixed Returns the result of the last callable or if no handlers are set the
     *               last result passed as argument if any. If an error occurs a WP_Error instance is returned.
     *
     * @throws InvocationException
     */
    protected function runHandlers(array $allHandlers, array $dependencies, bool $isToReturnResult = false): mixed
    {
        $result = null;
        foreach ($allHandlers as $handler) {
            try {
                $result = $this->getInvoker()->call($handler, $dependencies);
            } catch (ValidationException $e) {
                $result = new WpError(422, $e->getMessage(), ['errors' => $e->getErrors()]);
                $result = apply_filters('fastendpoints_request_error', $result, $e, $this);
            } catch (Exception $e) {
                $result = new WpError(500, $e->getMessage());
                $result = apply_filters('fastendpoints_request_error', $result, $e, $this);
            }

            if (is_wp_error($result) || $result instanceof WP_REST_Response) {
                return $result;
            }
        }

        return $isToReturnResult ? $result : null;
    }

    /**
     * @internal
     */
    public function getInvoker(): DI\Invoker
    {
        if ($this->invoker === null) {
            $this->invoker = new DI\Invoker;
        }

        return $this->invoker;
    }

    /**
     * @internal
     */
    public function getFullRoute(): string
    {
        return $this->fullRoute;
    }
}
