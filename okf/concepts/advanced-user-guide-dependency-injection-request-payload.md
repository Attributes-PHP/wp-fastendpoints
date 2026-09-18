---
type: concept
source: "https://wp-fastendpoints.attributes-php.com/advanced-user-guide/dependency-injection/request-payload/"
path: /advanced-user-guide/dependency-injection/request-payload/
updated: 2026-09-18
okf:
  generated_by: "@docmd/plugin-okf"
  generated_at: "2026-09-18T22:23:19.089Z"
---
As you might be already familiar WP-FastEndpoints allows you to fetch request data via function arguments, and optionally
you can validate that data via type-hinting.

### Built-in type-hints

By default, when you rely on built-in PHP types, DateTime, DateTimeInterface or when no type-hint is used,
WP-FastEndpoints will look for the data in the url or in the query parameters, in this order. However, if desired this
behaviour can be changed via *#[Attributes]*.

::: tabs

::: tab "Looks-up for URL or query parameter"

```php
<?php
$router->get('/posts', function (int $ID) {
    // Your logic
});
```

::: /tab

:::

::: tabs

::: tab "Looks-up for a header"

```php
<?php
use Attributes\Wp\FastEndpoints\Options\Header;

$router->get('/user', function (#[Header('Authorization')] string $token) {
    // Your logic
});
```

::: /tab

:::

The following is a list of all those type-hints:

- bool
- int
- float
- string
- callable
- array
- object
- [*DateTime*](https://www.php.net/manual/en/class.datetime.php)
- [*DateTimeInterface*](https://www.php.net/manual/en/class.datetimeinterface.php)

### Custom classes

For more complex types of data structures we can rely on custom classes. If you know how to write a class you know how
to validate a request in WP-FastEndpoints.

By default, when you rely on type-hints from custom classes WP-FastEndpoints will look for the data either in the
JSON or body of the current request. However, as with built-in type-hints, this behaviour can be changed via *#[Attributes]*.

```php
<?php
class HelloWorld {
    public string $name;
}
```

::: tabs

::: tab "Looks-up for JSON or body parameters"

```php
<?php
$router->post('/hello', function (HelloWorld $hello) {
    // Your logic
});
```

::: /tab

:::

::: tabs

::: tab "Looks-up for URL or query parameters"

```php
<?php
use Attributes\Wp\FastEndpoints\Options\Url;    
use Attributes\Wp\FastEndpoints\Options\Query;

$router->post('/hello', function (#[Url, Query] HelloWorld $hello) {
    // Your logic
});
```

::: /tab

:::

#### How to type-hint arrays?

Unfortunately, PHP doesn't provide a way to properly type-hint array's. Dictionaries aren't an issue because custom classes
can be used, but for sequenced arrays is a bit more tricky.

To solve this issue, WP-FastEndpoints assumes that any [*ArrayObject*](https://www.php.net/manual/en/class.arrayobject.php)
child class should be considered a *typed-hint* array. However, to actually type-hint that array a property name `$type`
with a type-hint needs to be specified in the class. Otherwise, an array of `mixed` is assumed.

```php
<?php
class HelloArr extends ArrayObject {
    public Hello $type;
}

$router->get('/say-hello', function (HelloArr $allHellos) {
    foreach ($allHellos as $hello) {
        echo "Hello $hello->name!";   
    }
});
```

::: callout tip
[attributes-php/validation](https://packagist.org/packages/Attributes-PHP/validation) provides some typed-arrays,
like: 1) [*BoolArr*](https://github.com/Attributes-PHP/validation/blob/main/src/Types/BoolArr.php),
2) [*IntArr*](https://github.com/Attributes-PHP/validation/blob/main/src/Types/IntArr.php),
3) [*StrArr*](https://github.com/Attributes-PHP/validation/blob/main/src/Types/StrArr.php) and
[others](https://github.com/Attributes-PHP/validation/tree/main/src/Types).
:::

### Special classes

When an argument has a type-hint it usually means that the request payload should follow that class structure. However,
there are three special classes which don't follow this pattern:

- [*WP_REST_Request*](https://developer.wordpress.org/reference/classes/wp_rest_request/) - Can be used to retrieve the
  current request
- [*WP_REST_Response*](https://developer.wordpress.org/reference/classes/wp_rest_response/) - Can be used to retrieve
  the response to be sent to the client, in case of a success. You could change the response HTTP status code or the data 
  which is sent to the client (wouldn't recommend the last one though).
- [*Endpoint*](https://github.com/Attributes-PHP/wp-fastendpoints/blob/main/src/Endpoint.php#L44) - The current endpoint
  instance. You shouldn't ever need this one.

::: tabs

::: tab "Get current request"

```php
<?php
$router->get('/request', function (WP_REST_Request $request) {
    return $request->get_params();
});
```

::: /tab

:::

::: tabs

::: tab "Change HTTP status code of a response"

```php
<?php
$router->get('/response/204', function (WP_REST_Response $response) {
    $response->set_status(204);
});
```

::: /tab

:::

### Required vs optional properties

Until now, we have seen most how to specify required properties from a request payload. In case your property is optional
you can rely on default values for that, like the following.

::: tabs

::: tab "Required property"

```php
<?php
$router->get('/required', function (int $id) {
    return $id;
});
```

::: /tab

:::

::: tabs

::: tab "Optional property"

```php
<?php
$router->get('/optional', function (int $id = 0) {
    return $id;
});
```

::: /tab

:::

::: tabs

::: tab "Optional payload"

```php
<?php
$router->get('/payload/optional', function (?HelloWorld $hello = null) {
    return $hello ? $hello->name : "No payload";
});
```

::: /tab

:::
