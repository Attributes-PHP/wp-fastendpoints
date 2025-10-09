<?php

/**
 * Holds logic to easily register WordPress endpoints that have the same base URL.
 *
 * @since 0.9.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints;

use Attributes\Wp\FastEndpoints\Contracts\Http\Endpoint as EndpointContract;
use Attributes\Wp\FastEndpoints\Contracts\Http\Router as RouterContract;

use function add_action;
use function apply_filters;
use function do_action;
use function esc_html__;
use function has_action;
use function trim;
use function wp_die;

/**
 * A Router can help developers in creating groups of endpoints. This way developers can aggregate
 * closely related endpoints in the same router. Example usage:
 *
 *      $usersRouter = new Router('users');
 *      $usersRouter->get(...); // Retrieve a user
 *      $usersRouter->put(...); // Update a user
 *
 *      $postsRouter = new Router('posts');
 *      $postsRouter->get(...); // Retrieve a post
 *      $postsRouter->put(...); // Update a post
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
class Router implements RouterContract
{
    /**
     * Router rest base
     */
    protected string $base;

    /**
     * Flag to determine if the current router has already being built.
     * This is important to prevent building a subRouter before the parent
     * finishes the building process
     */
    protected bool $registered = false;

    /**
     * Parent router
     */
    protected ?Router $parent = null;

    /**
     * Sub routers
     *
     * @var array<RouterContract>
     */
    protected array $subRouters = [];

    /**
     * REST Router endpoints
     *
     * @var array<Endpoint>
     */
    protected array $endpoints = [];

    /**
     * Router version used only if it's a parent router
     */
    protected string $version;

    /**
     * Required dependencies for this router
     */
    protected string|array|null $plugins = null;

    /**
     * Injectable dependencies that could be used in the associated endpoints
     *
     * @var array<string,callable>
     */
    protected array $injectables = [];

    /**
     * Set of functions used to handle exceptions
     *
     * @var array<string,callable>
     */
    protected array $onExceptionHandlers = [];

    /**
     * Creates a new Router instance
     *
     * @param  string  $base  Router base path if this router is the parent router would be used as
     *                        the namespace. Default value: 'api'.
     * @param  string  $version  Router version. Default value: ''.
     */
    public function __construct(string $base = 'api', string $version = '')
    {
        $this->base = $base;
        $this->version = $version;
    }

    /**
     * Adds a new GET endpoint
     *
     * @param  string  $route  Endpoint route.
     * @param  callable  $handler  User specified handler for the endpoint.
     * @param  array  $args  Same as the WordPress register_rest_route $args parameter. If set it can override the default
     *                       WP FastEndpoints arguments. Default value: [].
     * @param  bool  $override  Same as the WordPress register_rest_route $override parameter. Defaul value: false.
     */
    public function get(string $route, callable $handler, array $args = [], bool $override = false): EndpointContract
    {
        return $this->endpoint('GET', $route, $handler, $args, $override);
    }

    /**
     * Adds a new POST endpoint
     *
     * @param  string  $route  Endpoint route.
     * @param  callable  $handler  User specified handler for the endpoint.
     * @param  array  $args  Same as the WordPress register_rest_route $args parameter. If set it can override the default
     *                       WP FastEndpoints arguments. Default value: [].
     * @param  bool  $override  Same as the WordPress register_rest_route $override parameter. Default value: false.
     */
    public function post(string $route, callable $handler, array $args = [], bool $override = false): EndpointContract
    {
        return $this->endpoint('POST', $route, $handler, $args, $override);
    }

    /**
     * Adds a new PUT endpoint
     *
     * @param  string  $route  Endpoint route.
     * @param  callable  $handler  User specified handler for the endpoint.
     * @param  array  $args  Same as the WordPress register_rest_route $args parameter. If set it can override the default
     *                       WP FastEndpoints arguments. Default value: [].
     * @param  bool  $override  Same as the WordPress register_rest_route $override parameter. Default value: false.
     */
    public function put(string $route, callable $handler, array $args = [], bool $override = false): EndpointContract
    {
        return $this->endpoint('PUT', $route, $handler, $args, $override);
    }

    /**
     * Adds a new DELETE endpoint
     *
     * @param  string  $route  Endpoint route.
     * @param  callable  $handler  User specified handler for the endpoint.
     * @param  array  $args  Same as the WordPress register_rest_route $args parameter. If set it can override the default
     *                       WP FastEndpoints arguments. Default value: [].
     * @param  bool  $override  Same as the WordPress register_rest_route $override parameter. Default value: false.
     */
    public function delete(string $route, callable $handler, array $args = [], bool $override = false): EndpointContract
    {
        return $this->endpoint('DELETE', $route, $handler, $args, $override);
    }

    /**
     * Adds a new PATCH endpoint
     *
     * @param  string  $route  Endpoint route.
     * @param  callable  $handler  User specified handler for the endpoint.
     * @param  array  $args  Same as the WordPress register_rest_route $args parameter. If set it can override the default
     *                       WP FastEndpoints arguments. Default value: [].
     * @param  bool  $override  Same as the WordPress register_rest_route $override parameter. Default value: false.
     */
    public function patch(string $route, callable $handler, array $args = [], bool $override = false): EndpointContract
    {
        return $this->endpoint('PATCH', $route, $handler, $args, $override);
    }

    /**
     * Includes a router as a sub router
     *
     * @param  RouterContract  $router  REST sub router.
     */
    public function includeRouter(RouterContract &$router): void
    {
        $router->parent = $this;
        $this->subRouters[] = $router;
    }

    /**
     * Adds all actions required to register the defined endpoints
     */
    public function register(): void
    {
        if (! apply_filters('fastendpoints_is_to_register', true, $this)) {
            return;
        }

        if ($this->parent) {
            if (! has_action('rest_api_init', [$this->parent, 'registerEndpoints'])) {
                wp_die(esc_html__('You are trying to build a sub-router before building the parent router. \
					Call the build() function on the parent router only!'));
            }
        } else {
            if (! $this->base) {
                wp_die(esc_html__('No api namespace specified in the parent router'));
            }

            if (! $this->version) {
                wp_die(esc_html__('No api version specified in the parent router'));
            }

            do_action('fastendpoints_before_register', $this);
        }

        // Build current router endpoints.
        add_action('rest_api_init', [$this, 'registerEndpoints']);

        // Register each sub router, if any.
        foreach ($this->subRouters as $router) {
            if ($this->plugins !== null) {
                $router->depends($this->plugins);
            }

            foreach ($this->injectables as $name => $callable) {
                $router->inject($name, $callable);
            }

            foreach ($this->onExceptionHandlers as $exceptionClass => $handler) {
                $router->onException($exceptionClass, $handler);
            }

            $router->register();
        }

        if (! $this->parent) {
            do_action('fastendpoints_after_register', $this);
        }
    }

    /**
     * Adds a dependency which can then be injected in endpoints, middlewares or permission handlers.
     *
     * This should be useful to share common dependencies across multiple handlers e.g. database connection.
     * The dependency will be instantiated once, only!
     *
     *
     * @param  string  $name  The dependency name.
     * @param  callable  $handler  The handler which resolves the dependency.
     * @param  bool  $override  If set, overrides any existent dependency. Default value: false.
     */
    public function inject(string $name, callable $handler, bool $override = false): self
    {
        if (isset($this->injectables[$name]) && ! $override) {
            return $this;
        }

        $this->injectables[$name] = $handler;

        return $this;
    }

    /**
     * Adds a handler for a given exception.
     *
     * Handlers will be resolved on the following order: 1) by same exact exception or 2) by a parent class
     *
     * @param  string  $exceptionClass  The exception class to add a handler.
     * @param  callable  $handler  The handler to resolve those types of exceptions.
     * @param  bool  $override  If set, overrides any existent handlers. Default value: false.
     */
    public function onException(string $exceptionClass, callable $handler, bool $override = false): self
    {
        if (isset($this->onExceptionHandlers[$exceptionClass]) && ! $override) {
            return $this;
        }

        $this->onExceptionHandlers[$exceptionClass] = $handler;

        return $this;
    }

    /**
     * Registers the current router REST endpoints
     *
     * @internal
     */
    public function registerEndpoints(): void
    {
        $namespace = $this->getNamespace();
        $restBase = $this->getRestBase();
        foreach ($this->endpoints as $e) {
            if ($this->plugins !== null) {
                $e->depends($this->plugins);
            }

            foreach ($this->onExceptionHandlers as $exceptionClass => $handler) {
                $e->onException($exceptionClass, $handler);
            }

            $e->register($namespace, $restBase);
            $e->getInvoker()->setInjectables($this->injectables);
        }
        $this->registered = true;
    }

    /**
     * Retrieves all the attached endpoints
     *
     * @return array<Endpoint>
     */
    public function getEndpoints(): array
    {
        return $this->endpoints;
    }

    /**
     * Retrieves all attached sub-routers
     *
     * @return array<Router>
     */
    public function getSubRouters(): array
    {
        return $this->subRouters;
    }

    /**
     * Retrieves the base router namespace for each endpoint
     *
     * @param  bool  $isToApplyFilters  Flag used to ignore fastendpoints filters
     *                                  (i.e. this is needed to disable multiple calls to the filter given that it's a
     *                                  recursive function). Default value: true.
     */
    protected function getNamespace(bool $isToApplyFilters = true): string
    {
        if ($this->parent) {
            return $this->parent->getNamespace(false);
        }

        $namespace = trim($this->base, '/');
        if ($this->version) {
            $namespace .= '/'.trim($this->version, '/');
        }

        // Ignore recursive call to apply_filters without it, would be annoying for developers.
        if (! $isToApplyFilters) {
            return $namespace;
        }

        return apply_filters('fastendpoints_router_namespace', $namespace, $this);
    }

    /**
     * Retrieves the base REST path of the current router, if any. The base is what follows
     * the namespace and is before the endpoint route.
     */
    protected function getRestBase(): string
    {
        if (! $this->parent) {
            return '';
        }

        $restBase = trim($this->base, '/');
        if ($this->version) {
            $restBase .= '/'.trim($this->version, '/');
        }

        return apply_filters('fastendpoints_router_rest_base', $restBase, $this);
    }

    /**
     * Creates and retrieves a new endpoint instance
     *
     * @param  string  $method  POST, GET, PUT or DELETE or a value from WP_REST_Server (e.g. WP_REST_Server::EDITABLE).
     * @param  string  $route  Endpoint route.
     * @param  callable  $handler  User specified handler for the endpoint.
     * @param  array  $args  Same as the WordPress register_rest_route $args parameter. If set it can override the default
     *                       WP FastEndpoints arguments. Default value: [].
     * @param  bool  $override  Same as the WordPress register_rest_route $override parameter. Default value: false.
     */
    public function endpoint(
        string $method,
        string $route,
        callable $handler,
        array $args = [],
        bool $override = false
    ): EndpointContract {
        $endpoint = new Endpoint($method, $route, $handler, $args, $override);
        $this->endpoints[] = $endpoint;

        return $endpoint;
    }

    /**
     * Specifies a set of plugins that are needed by this router and all sub-routers
     */
    public function depends(string|array $plugins): self
    {
        if (is_string($plugins)) {
            $plugins = [$plugins];
        }

        $this->plugins = array_merge($this->plugins ?: [], $plugins);

        return $this;
    }
}
