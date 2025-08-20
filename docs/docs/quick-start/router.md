The first thing we need to do is to create a Router.

```php title="Api/Routers/Posts.php" hl_lines="4"
<?php
use Attributes\Wp\FastEndpoints\Router;

$router = new Router('posts');
```

A router is a class which allow us to attach and register endpoints.

An application can have one or multiple routers. One main benefit of using multiple routers is to group endpoints
by same namespace and (optionally) same version. For instance, in this tutorial we are going group all sub-routers,
into another router with a namespace and version `my-plugin/v1`

## Define the shape of the data

Each endpoint might require different types of data. Thanks to [Attributes-PHP/validation](https://github.com/Attributes-PHP/validation)
we can simply create our own PHP classes with the shape of the data we need and use them to validate our request payload
via type-hinting 🤯

```php title="Api/Models/Posts.php"
<?php
namespace MyPlugin\Api\Models;

use Attributes\Options\AliasGenerator;
use Attributes\Serialization\SerializableTrait;
use Respect\Validation\Rules;

enum Status: string
{
    case PUBLISH = 'publish';
    case DRAFT = 'draft';
    case PRIVATE = 'private';
}

#[AliasGenerator('snake')]
class Post
{
    use SerializableTrait;

    #[Rules\Positive]
    public int $ID;
    #[Rules\Positive]
    public int $postAuthor;
    public string $postTitle;
    public Status $postStatus;
}
```

1. Allows looking up for *snake* case properties e.g. post_author.
   More options at [Attributes-PHP/options »](https://github.com/Attributes-PHP/options)
2. Allow us to convert this instance into a dictionary via `$instance->serialize()`
3. Supports any [Respect Validation](https://respect-validation.readthedocs.io/en/2.4/) rules

## Create a post

Let's now create an endpoint which needs this type of data.

```php title="Api/Routers/Posts.php"
<?php
use MyPlugin\Api\Models\Post;

$router->post('/', function (Post #(1) $post, WP_REST_Response #(2) $response) {
    $response->set_status(201);
    $payload = $post->serialize();

    return wp_insert_post($payload, true);
})
    ->hasCap('publish_posts');
```

1. By default, class-based type-hints will look for the data either in *[get_json_params](https://developer.wordpress.org/reference/classes/wp_rest_request/get_json_params/)* or
   *[get_body_params](https://developer.wordpress.org/reference/classes/wp_rest_request/get_body_params/)*. To change
   this behavior see [Dependency injection]()
2. Custom dependencies can also be injected via `$router->inject`. See [inject custom dependencies]()

When a request is received by this endpoint the following happens:

1. First, the user permissions are checked - ensuring that only users with the [*publish_posts*](https://wordpress.org/documentation/article/roles-and-capabilities/#publish_posts) capability are 
   able to trigger this endpoint 
2. Second, if successful, the request payload is validated and populated into an instance of the `MyPlugin\Api\Models\Post`
   class. By default, **for classes only**, WP-FastEndpoints will look for the data either in
   [*get_json_params*](https://developer.wordpress.org/reference/classes/wp_rest_request/get_json_params/) or
   [*get_body_params*](https://developer.wordpress.org/reference/classes/wp_rest_request/get_body_params/), depending on the
   type of request. This behaviour can be changed via attributes though, see [this page]() for more info.
3. Third, the handler is called and creates a new blog post.

## Retrieve a post

A great thing of dependency injection is that you only type what you need. And if you only need the ID of a post, so be it 😊

```php title="Api/Routers/Posts.php"
<?php
use Attributes\Wp\FastEndpoints\Helpers\WpError;
use Respect\Validation\Rules;
use MyPlugin\Api\Models\Post;

$router->get('(?P<ID>[\d]+)', function (#[Rules\Positive] #(1) int $ID) {
    $post = get_post($ID);

    return $post ?: new WpError(404, 'Post not found'); #(2)
})
    ->returns(Post::class)
    ->hasCap('read');
```

1. Yes, endpoint arguments do support any [Respect Validation](https://respect-validation.readthedocs.io/en/2.4/) rules
2. [WpError](https://github.com/Attributes-PHP/wp-fastendpoints/blob/main/src/Helpers/WpError.php) is simply a subclass
   of WP_Error which automatically set's the HTTP status code on the response data as well

When a request is received, the following happens:

1. First, we ensure the user has the [_read_](https://wordpress.org/documentation/article/roles-and-capabilities/#read) capability
2. Second, we ensure that the ID parameter is a valid positive integer. By default, built-in type-hints lookup for
   the data in the following order: 1) [*get_url_params*](https://developer.wordpress.org/reference/classes/wp_rest_request/get_url_params/)
   and then 2) [*get_query_params*](https://developer.wordpress.org/reference/classes/wp_rest_request/get_query_params/)
3. Lastly, if neither WP_Error or WP_REST_Response is returned by the handler, the `returns(Post::class)` will ensure
   that the response sent to the client will only contain the fields specified in the `MyPlugin\Api\Models\Post` class. You
   could also ignore some fields via `Attributes\Options\Ignore` attribute from [Attributes-PHP/options](https://github.com/Attributes-PHP/options)

## Delete a post

A common scenario while building API's is to ensure that a user has permissions to a particular resource, in this
case a blog post.

```php title="Api/Routers/Posts.php" hl_lines="10"
<?php
use Attributes\Wp\FastEndpoints\Helpers\WpError;
use Respect\Validation\Rules;
use MyPlugin\Api\Models\Post;

$router->delete('(?P<ID>[\d]+)', function (#[Rules\Positive] int $ID) {
    return wp_delete_post($ID) ?: new WpError(500, 'Unable to delete post');
})
    ->returns(Post::class)
    ->hasCap('delete_post', '<ID>');
```

In this scenario, only user's with permissions to delete the specific blog post with the provided `<ID>` would be able
to successfully trigger this endpoint.

## Everything together

```php title="Api/Routers/Posts.php"
<?php
/* Holds REST endpoints to interact with blog posts */

declare(strict_types=1);

use Attributes\Wp\FastEndpoints\Helpers\WpError;
use Attributes\Wp\FastEndpoints\Router;
use MyPlugin\Api\Models\Post;
use Respect\Validation\Rules;

$router = new Router('posts');

$router->post('/', function (Post $post, WP_REST_Response $response) {
    $response->set_status(201);
    $payload = $post->serialize();

    return wp_insert_post($payload, true);
})
    ->hasCap('publish_posts');

$router->get('(?P<ID>[\d]+)', function (#[Rules\Positive] int $ID) {
    $post = get_post($ID);

    return $post ?: new WpError(404, 'Post not found');
})
    ->returns(Post::class)
    ->hasCap('read');

$router->delete('(?P<ID>[\d]+)', function (#[Rules\Positive] int $ID) {
    return wp_delete_post($ID) ?: new WpError(500, 'Unable to delete post');
})
    ->returns(Post::class)
    ->hasCap('delete_post', '<ID>');

// IMPORTANT: If no service provider is used make sure to set a version to the $router and call
//            the following function here:
// $router->register();

// Used later on by the ApiProvider
return $router;
```