<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when eNom cannot be reached or keeps answering with server errors after
 * every retry. The message never contains the request body or credentials.
 */
class EnomUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'eNom is unavailable', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
