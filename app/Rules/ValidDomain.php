<?php

namespace App\Rules;

use App\Support\DomainNormaliser;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Passes when the value normalises to a valid canonical domain (variants accepted).
 * Null/empty values are skipped; combine with required/nullable rules.
 */
class ValidDomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || DomainNormaliser::tryNormalise($value) === null) {
            $fail('The :attribute must be a valid domain name.');
        }
    }
}
