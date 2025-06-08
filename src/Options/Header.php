<?php

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints\Options;

use Attribute;
use WP_REST_Request;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Header implements From
{
    public function getParams(WP_REST_Request $request): array
    {
        $allHeaders = [];
        foreach ($request->get_headers() as $name => $value) {
            $allHeaders[$name] = $request->get_header($name);
        }

        return $allHeaders;
    }
}
