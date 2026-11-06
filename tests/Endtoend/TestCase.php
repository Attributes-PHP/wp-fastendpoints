<?php

/**
 * Base test case for end-to-end tests, which send real HTTP requests to the
 * WordPress REST API served by wp-env.
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints\Tests\Endtoend;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use WP_Application_Passwords;

abstract class TestCase extends PHPUnitTestCase
{
    /**
     * Credentials of the user the test is running as, as [user login, application password].
     */
    private ?array $credentials = null;

    /**
     * IDs of the users created during a test
     *
     * @var array<int>
     */
    protected array $userIds = [];

    /**
     * IDs of the posts created during a test
     *
     * @var array<int>
     */
    protected array $postIds = [];

    protected function tearDown(): void
    {
        if (! function_exists('wp_delete_user') && defined('ABSPATH')) {
            require_once ABSPATH.'wp-admin/includes/user.php';
        }

        foreach ($this->postIds as $postId) {
            wp_delete_post($postId, true);
        }
        foreach ($this->userIds as $userId) {
            wp_delete_user($userId);
        }
        $this->postIds = [];
        $this->userIds = [];
        $this->credentials = null;
    }

    /**
     * Creates a new user, which is deleted when the test ends
     */
    protected function createUser(): int
    {
        $login = 'test-user-'.uniqid();
        $userId = wp_create_user($login, wp_generate_password(), "{$login}@example.com");
        if (is_wp_error($userId)) {
            throw new \RuntimeException('Unable to create test user: '.$userId->get_error_message());
        }
        $this->userIds[] = $userId = (int) $userId;

        return $userId;
    }

    /**
     * Creates a new post, which is deleted when the test ends
     *
     * @param  array  $args  Arguments used to create the post. Default: a published post.
     */
    protected function createPost(array $args = []): int
    {
        $args += ['post_title' => 'My title', 'post_status' => 'publish'];
        $postId = wp_insert_post($args, true);
        if (is_wp_error($postId)) {
            throw new \RuntimeException('Unable to create test post: '.$postId->get_error_message());
        }
        $this->postIds[] = $postId = (int) $postId;

        return $postId;
    }

    /**
     * Makes the requests sent during the test run as the given user, using an application password
     */
    protected function authenticateAs(int $userId): void
    {
        $create = method_exists(WP_Application_Passwords::class, 'create_new_application_password')
            ? 'create_new_application_password'
            : 'create_new_password';
        [$password] = WP_Application_Passwords::$create($userId, ['name' => 'pest']);
        $user = get_user_by('id', $userId);
        $this->credentials = [$user->user_login, $password];
    }

    /**
     * Retrieves the route keys of the given namespace, e.g. "/my-posts/v1/..."
     */
    protected function getRoutes(string $namespace): array
    {
        [$status, $data] = $this->request('GET', "/{$namespace}");
        if ($status !== 200) {
            throw new \RuntimeException("Unable to retrieve the routes of the {$namespace} namespace");
        }

        return array_keys($data['routes']);
    }

    /**
     * Sends a real HTTP request to the REST API served by wp-env
     *
     * @param  string  $method  Request method, e.g. GET or POST.
     * @param  string  $path  REST API path, e.g. "/my-posts/v1/10".
     * @param  array  $args  Optional. Supports:
     *                       - "query": array of query params
     *                       - "headers": array of request headers
     *                       - "json": array to be sent as a JSON body
     *                       - "form": array to be sent as a urlencoded body
     *                       - "body": raw request body
     * @return array The response status code and its decoded JSON body.
     */
    protected function request(string $method, string $path, array $args = []): array
    {
        $url = rest_url(ltrim($path, '/'));
        if (! empty($args['query'])) {
            $url = add_query_arg($args['query'], $url);
        }

        $headers = $args['headers'] ?? [];
        if (array_key_exists('json', $args)) {
            $headers['Content-Type'] = 'application/json';
            $body = json_encode($args['json']);
        } elseif (array_key_exists('form', $args)) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            $body = http_build_query($args['form']);
        } else {
            $body = $args['body'] ?? null;
        }

        if ($this->credentials) {
            $headers['Authorization'] = 'Basic '.base64_encode(implode(':', $this->credentials));
        }

        $headerLines = array_map(
            fn (string $name, string $value) => "{$name}: {$value}",
            array_keys($headers),
            $headers,
        );

        $request = curl_init($url);
        curl_setopt_array($request, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($body !== null) {
            curl_setopt($request, CURLOPT_POSTFIELDS, $body);
        }
        $responseBody = curl_exec($request);
        if ($responseBody === false) {
            $error = curl_error($request);
            curl_close($request);
            throw new \RuntimeException('Unable to reach the REST API: '.$error);
        }
        $status = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
        curl_close($request);

        return [
            $status,
            json_decode($responseBody, true),
        ];
    }
}
