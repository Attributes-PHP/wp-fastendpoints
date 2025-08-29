To allow us unit test our router we would need to update the following line:

```php title="src/Api/Routers/Posts.php" hl_lines="3"
<?php

$router = $router ?? new Router('posts');
```

This change will allow us to pass a mocked router to easily test our endpoints.

## Create a post

As an example we are going to create a unit test to ensure that the correct user permissions are set.

```php title="tests/Unit/PostsApiTest.php"
<?php
test('Create post has correct permissions', function () {
    // Create endpoint mock
    $endpoint = $endpoint ?: Mockery::mock(Endpoint::class);
    $endpoint
        ->shouldReceive('hasCap')
        ->once()
        ->with('publish_posts');
    // Create router
    $router = Mockery::mock(Router::class)
        ->shouldIgnoreMissing(Mockery::mock(Endpoint::class)->shouldIgnoreMissing(Mockery::self()));
    $router
        ->shouldReceive('post')
        ->once()
        ->with('/', Mockery::type('callable'))
        ->andReturn($endpoint);
    require \ROUTERS_DIR.'/Posts.php';
})->group('api', 'posts');
```

Please refer to the **[Attributes-PHP/wp-fastendpoints-my-plugin »](https://github.com/Attributes-PHP/wp-fastendpoints-my-plugin)** for full source code
