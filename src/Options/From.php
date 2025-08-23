<?php

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints\Options;

use WP_REST_Request;

interface From
{
    public function getParams(WP_REST_Request $request): array;
}
