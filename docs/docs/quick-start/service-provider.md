Now that we have our endpoints ready, the last step is to register them. You can register a router individually or
by grouping all of them into a single *main* router and register only that main router. The latter is what we are going to do. 

```php title="Providers/ApiProvider.php"
<?php
declare(strict_types=1);

namespace MyPlugin\Providers;

use Attributes\Wp\FastEndpoints\Router;

class ApiProvider implements ProviderContract
{
    protected Router $appRouter;

    public function register(): void
    {
        $this->appRouter = new Router('my-plugin', 'v1');
        foreach (glob(\ROUTERS_DIR.'/*.php') as $filename) {
            $router = require $filename;
            $this->appRouter->includeRouter($router);  #(1)
        }
        $this->appRouter->register(); #(2)
    }
}
```

1. By including a router the namespace and version of the parent router will be inherited e.g. /my-plugin/v1/posts/(?P<ID>[\d]+)
2. Internally, this function relies on the [*rest_api_init*](https://developer.wordpress.org/reference/hooks/rest_api_init/) hook.

## It's running

🎉 Congrats you just created your first set of WP FastEndpoints

Now let's see [how to test them](https://github.com/Attributes-PHP/wp-fastendpoints/wiki/Testing)! 😄

Full source code can be found at **[attributes-php/wp-fastendpoints-my-plugin »](https://github.com/Attributes-PHP/wp-fastendpoints-my-plugin)**