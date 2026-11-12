<?php

namespace App\Exceptions;

use InvalidArgumentException;

class InvalidDomainException extends InvalidArgumentException
{
    public function __construct(public readonly string $input, ?string $reason = null)
    {
        parent::__construct('Invalid domain name: "' . $input . '"' . ($reason ? " ({$reason})" : ''));
    }
}
