<?php

namespace App\Exceptions;

use RuntimeException;

class WhmProvisioningException extends RuntimeException
{
    public function __construct(string $message = 'Failed to provision hosting account', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
