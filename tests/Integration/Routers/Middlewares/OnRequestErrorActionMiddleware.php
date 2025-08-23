<?php

namespace Attributes\Wp\FastEndpoints\Tests\Integration\Routers\Middlewares;

use Attributes\Wp\FastEndpoints\Contracts\Middlewares\Middleware;
use Attributes\Wp\FastEndpoints\Helpers\WpError;

class OnRequestErrorActionMiddleware extends Middleware
{
    public function onRequest(string $action): ?\WP_Error
    {
        return $action !== 'error' ? null : new WpError(469, 'Triggered error action before handling request');
    }
}
