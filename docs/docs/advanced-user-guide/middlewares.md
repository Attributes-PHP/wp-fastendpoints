Another cool feature of WP-FastEndpoints is the support for middlewares.

Middlewares are pieces of code that can either run before and/or after a request is handled.

At this stage, you might be already familiar with `returns(...)` which is a middleware.
However, you can also create your own.

```php hl_lines="10 18 26"
<?php
use Attributes\Wp\FastEndpoints\Contracts\Middleware;

class MyCustomMiddleware extends Middleware
{
    /**
    * This function is triggered before the main handler runs
    * but after checking the user permissions.
    */
    public function onRequest(#(1))) {
        return; #(2)
    }
    
    /**
    * This function is triggered after the main handler,
    * before sending a response to the client 
    */
    public function onResponse(#(3)) {
        return; #(4)
    }
}

$router->get('/test', function () {
    return true;
})
->middleware(new MyCustomMiddleware());
```

1. Supports all features that a regular endpoint supports
   e.g. [injectables](/advanced-user-guide/dependency-injection/injectables),
   [typed request data](/advanced-user-guide/dependency-injection/request-payload)
2. Early response return is also supported. See [Responses page](/advanced-user-guide/responses)
3. Middlewares supports all features that a regular endpoint supports
   e.g. [injectables](/advanced-user-guide/dependency-injection/injectables),
   [typed request data](/advanced-user-guide/dependency-injection/request-payload)
4. Early response return is also supported. See [Responses page](/advanced-user-guide/responses)

???+ tip
    You can create both methods in a middleware: `onRequest` and `onResponse`.
    However, to save some CPU cycles only create the one you need [CPU emoji]
