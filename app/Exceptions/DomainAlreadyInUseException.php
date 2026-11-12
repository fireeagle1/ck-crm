<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when a hosting service cannot be created because a different service
 * (including a Cancelled one) already holds the canonical domain.
 */
class DomainAlreadyInUseException extends RuntimeException
{
    public function __construct(
        public readonly string $domain,
        public readonly ?int $existingServiceId = null,
        ?Throwable $previous = null,
    ) {
        $holder = $existingServiceId !== null ? " by service #{$existingServiceId}" : '';

        parent::__construct("Domain [{$domain}] is already in use{$holder}.", 0, $previous);
    }
}
