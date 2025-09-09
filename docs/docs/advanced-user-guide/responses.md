When building an API sometimes we might want to return a response directly to the client. For example:

```php
<?php
$router->get('/posts/(?P<ID>[\d]+)', function (int $ID) {
    return get_post($ID);
})
->returns(Post::class); #(1)
```

1. A 422 HTTP error will be raised if unable to find a post because the data structure retrieved by the `*get_post*`
   function will not match the expected response structure e.g. *Post::class*.

## Early return

To avoid those scenarios we can either return a WP_Error or a WP_REST_Response.

=== "WP_REST_Response"
    ```php
    <?php
    $router->get('/posts/(?P<ID>[\d]+)', function (int $ID) {
        $post = get_post($ID);
        return $post ?: new WP_REST_Response("No posts found", 404);
    })
    ->returns(Post::class);  // This will not be triggered if no posts are found
    ```
=== "WP_Error"
    ```php
    <?php
    $router->get('/posts/(?P<ID>[\d]+)', function (int $ID) {
        $post = get_post($ID);
        return $post ?: new WpError(404, "No posts found");
    })
    ->returns(Post::class);  // This will not be triggered if no posts are found
    ```

### Difference between returning WP_REST_Response or WP_Error

The main difference between returning a WP_Error or a WP_REST_Response
is regarding the JSON returned in the body.

=== "WP_REST_Response"
    ```json
    "No posts found"
    ```
=== "WP_Error"
    ```json
    {
        "error": 404,
        "message": "No posts found",
        "data": {
            "status": 404
        }
    }
    ```
