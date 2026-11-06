---
title: "Integration Tests"
---

Integration tests, are a bit tricky to set up.

The following needs to happen in order to successfully run them:

1. Load WordPress
2. Replace the default _TestCase_ class with another one which eases testing in WordPress
   (e.g. to easily create users or posts)
3. Create the REST server and boot it using the [`rest_api_init`](https://developer.wordpress.org/reference/hooks/rest_api_init/)
   hook

### _wp-env_ to the rescue 🦸

However, thanks to [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)
most of this trouble is no longer an issue. It provides a WordPress install, the
PHPUnit test files and a database inside Docker, so integration tests become as
simple as: 😎

```bash
npm run test:integration
```

The pieces needed to get there:

1. Add wp-env as a dev dependency: `npm install --save-dev @wordpress/env`
2. Create a `.wp-env.test.json` file which maps your plugin into the test
   environment (a full example can be found in
   [wp-fastendpoints](https://github.com/Attributes-PHP/wp-fastendpoints/blob/main/.wp-env.test.json)):

```json title=".wp-env.test.json"
{
    "$schema": "https://schemas.wp.org/trunk/wp-env.json",
    "phpVersion": "8.1",
    "port": 8889,
    "mappings": {
        "wp-content/plugins/my-plugin": "."
    }
}
```

3. Create npm scripts to start the environment and run the integration tests
   inside it:

```json title="package.json"
{
    "scripts": {
        "wp-env-test": "wp-env --config .wp-env.test.json",
        "test:integration": "npm run wp-env-test -- start && npm run wp-env-test -- run --env-cwd='wp-content/plugins/my-plugin' wordpress vendor/bin/pest --colors=always --group=integration"
    }
}
```

To test against a specific WordPress version set the `WP_ENV_CORE` environment
variable to a wordpress.org zip, e.g. `WP_ENV_CORE=https://wordpress.org/wordpress-6.0.18.zip npm run test:integration`.

### SetUp and TearDown

The last step before creating our integration tests, is to set up the REST server and trigger the `*rest_api_init*` hook.

```php title="tests/Integration/PostsApiTest.php"
<?php
beforeEach(function () {
    parent::setUp();

    // Set up a REST server instance.
    global $wp_rest_server;
    $this->server = $wp_rest_server = new WP_REST_Server;
    do_action('rest_api_init', $this->server);
});

afterEach(function () {
    global $wp_rest_server;
    $this->server = $wp_rest_server = null;

    parent::tearDown();
});
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
        'post_author' => $userId,
    ]);
    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(201);
    $postId = $response->get_data();
    // Check that the post details are correct
    expect(get_post($postId))
        ->toBeInstanceOf(\WP_Post::class)
        ->toHaveProperty('post_title', 'My testing message')
        ->toHaveProperty('post_status', 'publish')
        ->toHaveProperty('post_author', $userId);
})->group('api', 'posts');
```

Here, we take advantage of the existent [testing factories](https://make.wordpress.org/core/handbook/testing/automated-testing/writing-phpunit-tests/#fixtures-and-factories)
to create a single user with the necessary capability to publish posts.
Then, we mimic a REST request from that given user, and lastly, we check if that
blog post was created.
