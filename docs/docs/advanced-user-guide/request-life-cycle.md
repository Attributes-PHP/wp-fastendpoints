In WP-FastEndpoints an endpoint can have multiple optional handlers attached to:

1. Permission handlers via `hasCap(...)` or `permission(...)` - Used to check for user permissions
2. Middlewares via `middleware(...)`
       1. Running before the handler being called and/or
       2. Running after the handler being called

## Permission handlers

When a request is received the first handlers to run are the permissions handlers. Permission handlers are called
by WordPress via [*`permission_callback`*](https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/#callbacks).

In contrast to WordPress, you can have one or multiple permission handlers attached to the same endpoint.

???+ note
    In the background all permission handlers are wrapped into one callable which is later on used as the
    `permission_callback` by the endpoint

These handlers will then be called in the same order as they were attached. For instance:

```php
<?php
$router->get('/test', function () {return true;})
    ->hasCap('read')              # Called first
    ->hasCap('edit_posts')        # Called second if the first one was successful
    ->permission('__return_true') # Called last if both the first and second were successful
```

## Middlewares

If all the permission handlers are successful the next set of handlers that run are the middlewares which
implement the `onRequest` function.

Remember that a middleware can implement `onRequest` and/or `onResponse` functions. The first one, runs before
the main endpoint handler and the later one runs after the main endpoint handler.

!!! warning
    Please bear in mind that if either a [WP_Error](https://developer.wordpress.org/reference/classes/wp_error/) or
    a [WP_REST_Response](https://developer.wordpress.org/reference/classes/wp_rest_response/) is returned by
    the main endpoint handler following middlewares will not run. See
    [Responses page](/wp-fastendpoints/advanced-user-guide/responses) for more info.

### onRequest

Same as with the permission handlers, middlewares are called with the same order that they were attached.

```php
<?php
class OnRequestMiddleware extends \Attributes\Wp\FastEndpoints\Contracts\Middleware
{
    public function onRequest(/* Type what you need */){
        return;
    }
}

$router->post('/test', function () {return true;})
    ->middleware(OnRequestMiddleware())    # Called first
    ->middleware(DoSomethingMiddleware()); # Called second
```

### onResponse

Likewise, middlewares implementing `onResponse` functions will be triggered in the same order as they were attached.

```php
<?php
use Attributes\Validation\Types\IntArr;

class OnResponseMiddleware extends \Attributes\Wp\FastEndpoints\Contracts\Middleware
{
    public function onResponse(/* Type what you need */){
        return;
    }
}

$router->post('/test', function () {return [1,2,3];})
    ->returns(IntArr::class)              # Called first
    ->middleware(OnResponseMiddleware()); # Called second
```
