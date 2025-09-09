A common scenario while creating API's is the need to share resources or logic across multiple endpoints or handlers.
This is where *injectables* can be useful.

```php hl_lines="5 12"
<?php
use Attributes\Validation\Validatable;
use Attributes\Wp\FastEndpoints\Options\Inject;

$router->inject('user', function (): WP_User {
    if (! is_user_logged_in()) {
        return new WpError(401, "Ups! You need to login first");
    }
    return wp_get_current_user();
});

$router->get('/posts', function (#[Inject] WP_User $user) {
    // Your logic
}));

$router->get('/user', function (#[Inject] WP_User $user) {
    // Your logic
});
```

#### Function names

If an injectable is not found, FastEndpoints will try to look-up for a function with the same property name or specified
injectable name.

```php hl_lines="4"
<?php
function hello_world() { return "Hello world!"; }

$router->get('/hello', function (#[Inject('hello_world')] string $hello) {
    return $hello;
}));
```

#### How it resolves injectables?

Each injectable is only resolved once while handling a request. For instance, if the same injectable is used in both
the permission callback and the endpoint, it will first be resolved during the permission callback and then re-use the
cached value for subsequent calls.

```php hl_lines="9 12"
<?php
$router->inject('nextNumber', function (): int {
    global $nextNumber;
    $randomNumber = ($randomNumber ?: 0) + 1;
    return $randomNumber;
});

$router->get('/posts', function (#[Inject] int $nextNumber) {
    // $nextNumber will be 1
}))
->permission(function(#[Inject] int $nextNumber) {
    // $nextNumber will be 1
    return true;
});
```