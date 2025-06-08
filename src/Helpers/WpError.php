<?php

/**
 * Holds Class that removes repeating http status in $data.
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints\Helpers;

use WP_Error;

class WpError extends WP_Error
{
    /**
     * @param  int  $statusCode  HTTP error code
     * @param  string|array  $message  The error message
     * @param  array  $data  Additional data to be sent
     */
    public function __construct(int $statusCode, string|array $message, array $data = [])
    {
        $data = $this->getData($data, $statusCode, $message);
        $firstMessage = $this->getFirstErrorMessage($message);
        parent::__construct($statusCode, $firstMessage, $data);
    }

    protected function getData(array $data, int $statusCode, string|array $message): array
    {
        if (is_array($message)) {
            $data['all_messages'] = $message;
        }

        return array_merge(['status' => $statusCode], $data);
    }

    /**
     * Gets the first message from an array
     */
    protected function getFirstErrorMessage(string|array $message): string
    {
        if (! is_array($message)) {
            return __($message);
        }

        if (count($message) == 0) {
            return __('No error description provided');
        }

        while (is_array($message)) {
            $message = reset($message);
        }

        if ($message === false) {
            return __('No error description provided');
        }

        return __($message);
    }
}
