<?php

namespace App\Services;

use App\Models\Domain;
use Carbon\CarbonInterface;

/**
 * Tracks which eNom-owned domain fields an admin has edited so the eNom sync
 * leaves them alone (Req 13.3). The sync itself must not call this.
 */
class DomainEditTracker
{
    /**
     * Merge every ENOM_FIELDS key in $changes whose value differs from the
     * domain's current value into admin_edited_fields. Call before applying
     * the update. Other keys are ignored.
     */
    public function record(Domain $domain, array $changes): void
    {
        $edited = [];

        foreach (Domain::ENOM_FIELDS as $field) {
            if (! array_key_exists($field, $changes)) {
                continue;
            }

            if ($this->normalise($field, $changes[$field]) !== $this->normalise($field, $domain->getAttribute($field))) {
                $edited[] = $field;
            }
        }

        if ($edited === []) {
            return;
        }

        $merged = array_values(array_unique(array_merge($domain->admin_edited_fields ?? [], $edited)));

        $this->persist($domain, $merged);
    }

    /**
     * Clear the flags: all of them, or only the given fields.
     */
    public function reset(Domain $domain, ?array $fields = null): void
    {
        $remaining = $fields === null
            ? []
            : array_values(array_diff($domain->admin_edited_fields ?? [], $fields));

        $this->persist($domain, $remaining === [] ? null : $remaining);
    }

    private function persist(Domain $domain, ?array $fields): void
    {
        if (! $domain->exists) {
            $domain->admin_edited_fields = $fields;

            return;
        }

        Domain::withTrashed()->whereKey($domain->getKey())->update([
            'admin_edited_fields' => $fields === null ? null : json_encode($fields),
        ]);

        $domain->setAttribute('admin_edited_fields', $fields);
        $domain->syncOriginalAttribute('admin_edited_fields');
    }

    private function normalise(string $field, mixed $value): mixed
    {
        if ($field === 'auto_renew') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if ($value === null || $value === '') {
            return null;
        }

        if ($field === 'expiry_date') {
            try {
                return ($value instanceof CarbonInterface ? $value : \Illuminate\Support\Carbon::parse($value))->toDateString();
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        return trim((string) $value);
    }
}
