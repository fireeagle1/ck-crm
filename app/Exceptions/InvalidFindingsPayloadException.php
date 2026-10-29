<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a findings ingestion payload fails the explicit allow-list
 * validation (a package missing name/version, or a finding missing
 * severity/description or carrying a status outside SecurityFinding::STATUSES).
 *
 * Carries the list of offending field messages so the caller can map it to a
 * validation error response (Req 3.4). No records are persisted when this is
 * thrown.
 */
class InvalidFindingsPayloadException extends RuntimeException
{
    /** @var array<int, string> */
    private array $errors;

    /**
     * @param array<int, string> $errors
     */
    public function __construct(array $errors)
    {
        $this->errors = array_values($errors);

        parent::__construct(
            'Findings payload failed validation: ' . implode('; ', $this->errors)
        );
    }

    /**
     * @return array<int, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
