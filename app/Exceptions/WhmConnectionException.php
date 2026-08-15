<?php

namespace App\Exceptions;

use RuntimeException;

class WhmConnectionException extends RuntimeException
{
    public function __construct(string $message = 'Failed to connect to WHM server', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
