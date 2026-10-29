<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a findings ingestion request targets an app identifier that
 * does not resolve to an existing CustomerApp.
 *
 * The message names the unknown identifier so the caller (HTTP endpoint or
 * Artisan command) can surface an error that identifies the app (Req 3.3).
 * No records are persisted when this is thrown.
 */
class UnknownCustomerAppException extends RuntimeException
{
    public static function forIdentifier(string $identifier): self
    {
        return new self("No customer app matches identifier [{$identifier}].");
    }
}
