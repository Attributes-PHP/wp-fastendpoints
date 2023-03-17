<?php

/**
 * Holds an interface for checking/parsing the REST response of an endpoint
 *
 * @version 1.0.0
 * @package wp-fastendpoints
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Contracts\Schemas;

use WP_REST_Request;

/**
 * Response interface for parsing/checking the REST response of an endpoint before sending it to the client.
 *
 * @version 1.0.0
 * @author André Gil <andre_gil22@hotmail.com>
 */
interface ResponseInterface
{
    /**
     * Parses and checks the data to be sent back to the client.
     *
     * @version 1.0.0
     * @param WP_REST_Request $req - Current REST Request.
     * @param mixed $res - Current REST response.
     * @return mixed|WP_Error - Mixed on parsed response or WP_Error on error.
     */
    public function returns(WP_REST_Request $req, $res);

    /**
     * Appends an additional directory where to look for the schema
     *
     * @version 1.0.0
     * @param string|array<string> $schemaDir - Directory path or an array of directories where to
     * look for JSON schemas.
     */
    public function appendSchemaDir($schemaDir): void;
}
