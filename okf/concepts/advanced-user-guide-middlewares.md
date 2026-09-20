---
type: concept
title: Middlewares
source: "https://wp-fastendpoints.attributes-php.com/advanced-user-guide/middlewares/"
path: /advanced-user-guide/middlewares/
updated: 2026-09-20
okf:
  generated_by: "@docmd/plugin-okf"
  generated_at: "2026-09-20T11:08:54.690Z"
---
---
title: "Middlewares"
---

Another cool feature of WP-FastEndpoints is the support for middlewares.

Middlewares are pieces of code that can either run before and/or after a request is handled.

At this stage, you might be already familiar with `returns(...)` which is a middleware.
However, you can also create your own.

```php
<?php
use Attributes\Wp\FastEndpoints\Contracts\Middleware;

class MyCustomMiddleware extends Middleware
{
    /**
    * This function is triggered before the main handler runs
    * but after checking the user permissions.
    */
    public function onRequest(/* (1) */)) {
        return; /* (2) */
    }
    
    /**
    * This function is triggered after the main handler,
    * before sending a response to the client 
    */
    public function onResponse(/* (3) */) {
        return; /* (4) */
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

::: collapsible open "Tip"
You can create both methods in a middleware: `onRequest` and `onResponse`.
However, to save some CPU cycles only create the one you need [CPU emoji]
:::
