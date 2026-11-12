<?php

namespace App\Http\Requests\Concerns;

use App\Support\DomainNormaliser;

/**
 * For FormRequests: replaces domain input with its canonical form before validation.
 * Invalid values are left untouched so ValidDomain reports the field error.
 */
trait NormalisesDomain
{
    /** @return list<string> Input keys holding domain names. */
    protected function domainFields(): array
    {
        return ['domain_name'];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach ($this->domainFields() as $field) {
            $value = $this->input($field);
            if (is_string($value) && ($canonical = DomainNormaliser::tryNormalise($value)) !== null) {
                $merge[$field] = $canonical;
            }
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
