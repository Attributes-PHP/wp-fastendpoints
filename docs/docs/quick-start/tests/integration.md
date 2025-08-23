Integration tests, are a bit tricky to set up.

The following needs to happen in order to successfully run them:

1. Load WordPress
2. Replace the default _TestCase_ class with another with enhanced WordPress functionalities
   (e.g. to easily create users or posts)
3. Create the REST server and boot it using the [`rest_api_init`](https://developer.wordpress.org/reference/hooks/rest_api_init/)
   hook

### _wp-pest_ to the rescue 🦸

However, thanks to [wp-pest](https://github.com/dingo-d/wp-pest) most of this trouble is no longer
an issue. Via a simple command it does all of that for us! 😎

```bash
# Installs wp-pest
composer require dingo-d/wp-pest --dev

# Set's up WP
./vendor/bin/wp-pest setup plugin --plugin-slug my-plugin --wp-version 6.4.4
```

!!! tip
    If you use [attributes-php/wp-fastendpoints-my-plugin](https://github.com/Attributes-PHP/wp-fastendpoints-my-plugin?tab=readme-ov-file#setup-wordpress)
    you can use the already configured `composer setup:wp:6.x` commands

#### Optional changes

If you take a closer look at the resultant tests structure you might notice that is slightly
different from [attributes-php/wp-fastendpoints-my-plugin](https://github.com/Attributes-PHP/wp-fastendpoints-my-plugin?tab=readme-ov-file#setup-wordpress).
These changes are not mandatory and so, feel free to skip this section ⏩

The main reason of these differences is to allow us to run tests without the
need to always specify a group of tests. Those changes include:

```php title="tests/Helpers.php"
<?php
declare(strict_types=1);
 
namespace MyPlugin\Tests;

class Helpers
{
    /**
     * Checks if weather we want to run integration tests or not
     */
    public static function isIntegrationTest(): bool
    {
        return isset($GLOBALS['argv']) && in_array('--group=integration', $GLOBALS['argv'], true);
    }
}
```

```php title="tests/Integration/PostsApiTest.php"
<?php
declare(strict_types=1);

namespace MyPlugin\Tests\Integration;

use MyPlugin\Tests\Helpers;

// Needs to add this check to every Integration test file
if (! Helpers::isIntegrationTest()) {
    return;
}
```

### Our integration test 🙃

Now that everything is configured we can start creating integration tests:

```php title="tests/Integration/PostsApiTest.php"
<?php
test('Create a new post', function () {
    // Create user with correct permissions
    $userId = $this::factory()->user->create();
    $user = get_user_by('id', $userId);
    $user->add_cap('publish_posts');
    // Make request as that user
    wp_set_current_user($userId);
    $request = new \WP_REST_Request('POST', '/my-plugin/v1/posts');
    $request->set_body_params([
        'post_title' => 'My testing message',
        'post_status' => 'publish',
        'post_type' => 'post',
        'post_content' => '<p>Message body</p>',
    ]);
    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(201);
    $postId = $response->get_data();
    // Check that the post details are correct
    expect(get_post($postId))
        ->toBeInstanceOf(\WP_Post::class)
        ->toHaveProperty('post_title', 'My testing message')
        ->toHaveProperty('post_status', 'publish')
        ->toHaveProperty('post_type', 'post')
        ->toHaveProperty('post_content', '<p>Message body</p>');
})->group('api', 'posts');
```

Here, we take advantage of the existent [testing factories](https://make.wordpress.org/core/handbook/testing/automated-testing/writing-phpunit-tests/#fixtures-and-factories)
to create a single user with the necessary capability to publish posts.
Then, we mimic a REST request from that given user, and lastly, we check if that
blog post was created.
