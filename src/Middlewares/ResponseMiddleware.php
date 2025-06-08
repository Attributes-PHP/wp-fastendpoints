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
use Attributes\Validation\Context;
use Attributes\Validation\Exceptions\ValidationException;
use Attributes\Validation\Validatable;
use Attributes\Validation\Validator;
use Attributes\Wp\FastEndpoints\Contracts\Middlewares\Middleware;
use Attributes\Wp\FastEndpoints\Helpers\WpError;
use Attributes\Wp\FastEndpoints\Options\Inject;
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
     * The response validator
     */
    protected ?Validatable $validator;

    /**
     * @param  string|object  $schema  The class or instance to be used for validating responses
     *
     * @throws Exception
     */
    public function __construct(string|object $schema, ?Validatable $validator = null)
    {
        parent::__construct();
        $this->schema = $schema;
        $this->validator = $validator;
    }

    /**
     * Ensures that only the necessary properties are retrieved in a response
     *
     * @param  WP_REST_Request  $request  Current REST Request.
     * @param  mixed  $response  Current REST response.
     * @return null|WP_Error|WP_REST_Response null if nothing to change, WP_Error on error or WP_REST_Response for early return.
     */
    public function onResponse(WP_REST_Request $request, WP_REST_Response $response, #[Inject] Serializable $serializer): null|WP_Error|WP_REST_Response
    {
        $data = $this->getData($request, $response);
        if (is_wp_error($data) || $data instanceof WP_REST_Response) {
            return $data;
        }

        if (! is_object($data) && ! is_array($data)) {
            return new WpError(WP_Http::INTERNAL_SERVER_ERROR, 'Invalid response data. Expected \'array\' or \'object\' but '.gettype($data).' given.');
        }

        $schemaClass = is_object($this->schema) ? $this->schema::class : $this->schema;
        try {
            if (! ($data instanceof $schemaClass)) {
                $validator = $this->getValidator();
                $data = $validator->validate((array) $data, $this->schema);
            }
            $validData = $serializer->serialize($data);
            $response->set_data($validData);
        } catch (ValidationException $e) {
            $wpError = new WpError(WP_Http::INTERNAL_SERVER_ERROR, 'Invalid response', $e->getErrors());

            return apply_filters('fastendpoints_response_error', $wpError, $e, $response, $request, $this);
        } catch (SerializeException $e) {
            $wpError = new WpError(WP_Http::INTERNAL_SERVER_ERROR, sprintf(__('Unable to serialize response due to %s'), $e->getMessage()));

            return apply_filters('fastendpoints_response_error', $wpError, $e, $response, $request, $this);
        }

        return null;
    }

    protected function getData(WP_REST_Request $request, WP_REST_Response $response): mixed
    {
        $data = $response->get_data();
        if (is_object($data)) {
            if (method_exists($data, 'to_array')) {
                $data = $data->to_array();
            }
        }

        return apply_filters('fastendpoints_response_data', $data, $response, $request, $this);
    }

    protected function getValidator(): Validatable
    {
        if ($this->validator !== null) {
            return apply_filters('fastendpoints_response_validator', $this->validator, $this->schema);
        }

        $context = new Context;
        $context->set('internal.options.ignore.useSerialization', true);
        $this->validator = new Validator(context: $context);
        $this->validator = apply_filters('fastendpoints_response_validator', $this->validator, $this->schema);

        return $this->validator;
    }
}
