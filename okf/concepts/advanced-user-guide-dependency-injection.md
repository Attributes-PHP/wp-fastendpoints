---
type: concept
source: "https://wp-fastendpoints.attributes-php.com/advanced-user-guide/dependency-injection/"
path: /advanced-user-guide/dependency-injection/
updated: 2026-09-18
okf:
  generated_by: "@docmd/plugin-okf"
  generated_at: "2026-09-18T22:23:19.087Z"
---
Each REST endpoint has its unique logic. Same goes with the data that it needs to work.

For that reason, WP-FastEndpoints provides dependency injection support for all handlers
e.g. permission handlers, main endpoint handler and middlewares.

With dependency injection our endpoints do look much cleaner ✨🧹

::: tabs

::: tab "With dependency injection"

```php
<?php
// We only need the ID. So we type $ID
$router->get('/posts/(?P<ID>[\d]+)', function (int $ID) {
    return get_post($ID);
});

// We don't need anything. So no arguments are defined :D
$router->get('/posts/random', function () {
    $allPosts = get_posts();
    return $allPosts ? $allPosts[array_rand($allPosts)] : new WpError(404, 'No posts found');
});
```

::: /tab

:::

::: tabs

::: tab "No dependency injection"

```php
<?php
// Unable to fetch a dynamic parameter. Have to work with the $request argument
$router->get('/posts/(?P<ID>[\d]+)', function ($request) {
    return get_post($request->get('ID'));
});

// Forced to accept $request even if not used :(
$router->get('/posts/random', function ($request) {
    $allPosts = get_posts();
    return $allPosts ? $allPosts[array_rand($allPosts)] : new WpError(404, 'No posts found');
});
```

::: /tab

:::
