<?php

namespace Fastaar;

use Exception;

class FastaarException extends Exception
{
    /**
     * @param  string  $errorType  The stable API error code, e.g. "authentication_error",
     *                             "subscription_required", "transaction_limit_reached".
     */
    public function __construct(
        string $message,
        public readonly string $errorType = 'api_error',
        public readonly int $statusCode = 0,
    ) {
        parent::__construct($message, $statusCode);
    }
}
