<?php

/**
 * Holds logic check/parse the REST response of an endpoint. Making sure that the required data is sent
 * back and that no unnecessary fields are retrieved.
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints\Middlewares;

use Attributes\Serialization\Exceptions\SerializeException;
use Attributes\Serialization\Serializable;
use Attributes\Serialization\Serializer;
use Attributes\Validation\Exceptions\ValidationException;
use Attributes\Validation\Validatable;
use Attributes\Validation\Validator;
use Attributes\Wp\FastEndpoints\Contracts\Middlewares\Middleware;
use Attributes\Wp\FastEndpoints\Helpers\WpError;
use Exception;
use WP_Error;
use WP_Http;
use WP_REST_Request;
use WP_REST_Response;

/**
 * ResponseMiddleware class that checks/parses the REST response of an endpoint before sending it to the client.
 *
 * @author André Gil <andre_gil22@hotmail.com>
 */
class ResponseMiddleware extends Middleware
{
    /**
     * The class or instance to be used for validating responses
     */
    protected string|object $schema;

    /**
     * @param  string|object  $schema  The class or instance to be used for validating responses
     *
     * @throws Exception
     */
    public function __construct(string|object $schema)
    {
        parent::__construct();
        $this->schema = $schema;
    }

    /**
     * Ensures that only the necessary properties are retrieved in a response
     *
     * @param  WP_REST_Request  $request  Current REST Request.
     * @param  mixed  $response  Current REST response.
     * @return ?WP_Error null if nothing to change or WpError on error.
     */
    public function onResponse(WP_REST_Request $request, WP_REST_Response $response): ?WP_Error
    {
        $data = apply_filters('fastendpoints_response_data', $response->get_data(), $response, $request, $this);
        if (! is_array($data) && ! is_object($data)) {
            return new WpError(WP_Http::INTERNAL_SERVER_ERROR, 'Invalid response data. Expected \'array\' or \'object\' but '.gettype($data).' given.');
        }

        $className = is_string($this->schema) ? $this->schema : $this->schema::class;
        if ($data instanceof $className) {
            return null;
        }

        $validator = apply_filters('fastendpoints_response_validator', new Validator, $request, $response);
        if (! ($validator instanceof Validatable)) {
            return new WpError(WP_Http::INTERNAL_SERVER_ERROR, 'Invalid validator. Expected a \''.Validatable::class.'\' but '.gettype($validator).' given.');
        }
        $serializer = apply_filters('fastendpoints_response_serializer', new Serializer, $request, $response);
        if (! ($serializer instanceof Serializable)) {
            return new WpError(WP_Http::INTERNAL_SERVER_ERROR, 'Invalid serializer. Expected a \''.Serializable::class.'\' but '.gettype($serializer).' given.');
        }

        try {
            $validData = $validator->validate((array) $data, $this->schema);
            $validData = $serializer->serialize($validData);
            $response->set_data($validData);
        } catch (ValidationException $e) {
            $wpError = new WpError(WP_Http::INTERNAL_SERVER_ERROR, 'Invalid response', $e->getErrors());

            return apply_filters('fastendpoints_response_error', $wpError, $e, $response, $request, $this);
        } catch (SerializeException $e) {
            $wpError = new WpError(WP_Http::INTERNAL_SERVER_ERROR, sprintf(esc_html__('Unable to serialize response due to %s'), $e->getMessage()));

            return apply_filters('fastendpoints_response_error', $wpError, $e, $response, $request, $this);
        }

        return null;
    }
}
