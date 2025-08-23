<?php

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints\Options;

use Attribute;
use WP_REST_Request;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Body implements From
{
    public function getParams(WP_REST_Request $request): array
    {
        return $request->get_body_params();
    }
}
