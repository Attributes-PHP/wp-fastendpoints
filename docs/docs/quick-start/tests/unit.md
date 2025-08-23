To allow us unit test our router we would need to update the following line:

```php title="src/Api/Routers/Posts.php" hl_lines="3"
<?php

$router = $router ?? new Router('posts');
```

This change will allow us to pass a mocked router to easily test our endpoints.

## Create a post

As an example we are going to write a unit test to test our endpoint that creates a new blog post.

```php title="tests/Unit/PostsApiTest.php"
<?php
test('Creating post endpoint has correct permissions and schema', function () {
    // Create endpoint mock
    $endpoint = Mockery::mock(Endpoint::class);
    // Assert that user permissions are correct
    $endpoint
        ->shouldReceive('hasCap')
        ->once()
        ->with('publish_posts');
    // To ignore all the other endpoints
    $ignoreEndpoint = Mockery::mock(Endpoint::class)
        ->shouldIgnoreMissing(Mockery::self());
    // Create router. Make sure that var name matches your router variable
    $router = Mockery::mock(Router::class)
        ->shouldIgnoreMissing($ignoreEndpoint);
    // Assert that router endpoint is called
    $router
        ->shouldReceive('post')
        ->once()
        ->with('/', Mockery::type('callable'))
        ->andReturn($endpoint);
    // Needed to attach endpoints
    require \ROUTERS_DIR.'/Posts.php';
})->group('api', 'posts');
```

The reason we are able to make the assertions above is
[due to this line](https://github.com/Attributes-PHP/wp-fastendpoints/wiki/Quick-start#the-actual-code---srcapirouterspostsphp).
Specially, regarding this part ```$router ??```. This allows us to replace our original router with our mocked version.

Nothing magical happening here, just pure PHP code! 🪄
