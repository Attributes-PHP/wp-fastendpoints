The first thing we need to do is to create a Router.

```php title="Api/Routers/Posts.php"
use Attributes\Wp\FastEndpoints\Router;

// To allow us to mock this router in unit tests
$router = $router ?? new Router('posts');
```

A router is a class which allow us to attach and register endpoints.

An application can have one or multiple routers. One main benefit of using multiple routers is to group endpoints
by same namespace and (optionally) same version. For instance, in this tutorial we are going group all sub-routers,
into another router with a namespace and version `my-plugin/v1`

## Define the shape of our data

Each endpoint might require different types of data. Thanks to [Attributes-PHP/validation](https://github.com/Attributes-PHP/validation)
we can simply create our own PHP classes with the shape of the data we need and use them to validate our request payload
via type-hinting 🤯

```php title="Api/Models/Posts.php"
namespace MyPlugin\Api\Models\Post;

use Attributes\Serialization\SerializableTrait;
use Attributes\Options\AliasGenerator;
use Attributes\Options\Alias;
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

    #[Rules\Positive, Alias('ID')]
    public int $id;
    #[Rules\Positive]
    public int $postAuthor;
    public string $postTitle;
    public Status $postStatus;
}
```

## Create a post

With the shape of the data defined we can now use it in our endpoint to create a new blog post.

```php title="Api/Routers/Posts.php"
use MyPlugin\Api\Models\Post;

$router->post('/', function (Post $post): int|WP_Error {
    $response->set_status(201);
    $payload = $post->serialize();

    return wp_insert_post($payload, true);
})
    ->hasCap('publish_posts');
```

When a request is received by this endpoint the following happens:

1. Firstly, the user permissions are checked - Makes sure that the user has [*publish_posts*](https://wordpress.org/documentation/article/roles-and-capabilities/#publish_posts) capability
2. Then, if successful, it validates the request payload by using the *Posts/CreateOrUpdate* schema. 
   We still didn't explain where the endpoints should look for the schemas, but will get into that 
   in [Service Provider page](/wp-fastendpoints/quick-start/service-provider)
3. Lastly, if the validation process also passes the handler is called.

!!! info
    In this scenario we are not using a JSON schema to discard fields because the [_wp_insert_post_](https://developer.wordpress.org/reference/functions/wp_insert_post/)
    either returns the ID of the post or a WP_Error which is already what we want 😊

## Retrieve a post

Some endpoints however do need to return more complex objects. And in those cases JSON
schemas can be of a great help.

JSON schemas can help us to make sure that we are returning all the required fields
as well as to avoid retrieving sensitive information. The last one is configurable.

```php
use Attributes\Wp\FastEndpoints\Helpers\WpError;

$router->get('(?P<ID>[\d]+)', function ($ID) {
    $post = get_post($ID);

    return $post ?: new WpError(404, 'Post not found');
})
    ->returns('Posts/Get')
    ->hasCap('read');
```

In this case, we didn't set a JSON schema on purpose because we only need the
*post_id* which is already parsed by the regex rule - we could have made that rule
to match only positive integers though 🤔

Going back to the endpoint, this is what happens if a request comes in:

1. Firstly, it checks the user has [_read_](https://wordpress.org/documentation/article/roles-and-capabilities/#read)
   capability - one of the lowest WordPress users capabilities
2. If so, it then calls the handler which either retrieves the post data (e.g. array or object)
   or a [_WpError_](https://github.com/matapatos/wp-fastendpoints/blob/main/src/Helpers/WpError.php)
   in case that is not found. If a WpError or WP_Error is returned it stops further code execution
   and returns that error message to the client - avoiding triggering response schema validation for example.
3. Lastly, if the post data is returned by the handler the response schema will be triggered
   and will check the response according to the given schema (e.g. _Posts/Get_)

!!! note
    The [WpError](https://github.com/matapatos/wp-fastendpoints/blob/main/src/Helpers/WpError.php)
    is just a subclass of WP_Error which automatically set's the HTTP status code of the response

## Update a post

Checking for user capabilities such as `publish_posts` and `read` is cool. However, in the
real world we sometimes also need to check for a particular resource.

```php
$router->put('(?P<ID>[\d]+)', function (\WP_REST_Request $request): int|\WP_Error {
    $payload = $request->get_params();

    return wp_update_post($payload, true);
})
    ->schema('Posts/CreateOrUpdate')
    ->hasCap('edit_post', '<ID>');
```

The code above is not that different from the one for creating a post. However, in the last line
`hasCap('edit_post', '<ID>')` the second parameter is a special one for FastEndpoints
which will try to replace it by the _ID_ parameter.

!!! warning
    FastEndpoints will only replace the *{PARAM_NAME}* if that parameter
    exists in the request payload. Otherwise, will not touch it. Also, bear in mind that the first stage
    in an endpoint is checking the user capabilities. As such, at that time the request params have not
    been already validated by the request payload schema.

## Delete a post

```php
use Attributes\Wp\FastEndpoints\Helpers\WpError;

$router->delete('(?P<ID>[\d]+)', function ($ID) {
    $post = wp_delete_post($postId);

    return $post ?: new WpError(500, 'Unable to delete post');
})
    ->returns('Posts/Get')
    ->hasCap('delete_post', '<ID>');
```

## Everything together

```php
"""
Api/Endpoints/Posts.php
"""
declare(strict_types=1);

namespace MyPlugin\Api\Routers;

use Attributes\Wp\FastEndpoints\Helpers\WpError;
use Attributes\Wp\FastEndpoints\Router;

// Dependency injection to enable us to mock router in the tests
$router = $router ?? new Router('posts');

// Creates a post
$router->post('/', function (\WP_REST_Request $request): int|\WP_Error {
    $payload = $request->get_params();

    return wp_insert_post($payload, true);
})
    ->schema('Posts/CreateOrUpdate')
    ->hasCap('publish_posts');

// Fetches a single post
$router->get('(?P<ID>[\d]+)', function ($ID) {
    $post = get_post($ID);

    return $post ?: new WpError(404, 'Post not found');
})
    ->returns('Posts/Get')
    ->hasCap('read');

// Updates a post
$router->put('(?P<ID>[\d]+)', function (\WP_REST_Request $request): int|\WP_Error {
    $payload = $request->get_params();

    return wp_update_post($payload, true);
})
    ->schema('Posts/CreateOrUpdate')
    ->hasCap('edit_post', '<ID>');

// Deletes a post
$router->delete('(?P<ID>[\d]+)', function ($ID) {
    $post = wp_delete_post($postId);

    return $post ?: new WpError(500, 'Unable to delete post');
})
    ->returns('Posts/Get')
    ->hasCap('delete_post', '<ID>');

// IMPORTANT: If no service provider is used make sure to set a version to the $router and call
//            the following function here:
// $router->register();

// Used later on by the ApiProvider
return $router;
```