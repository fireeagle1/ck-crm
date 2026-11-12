<?php

namespace App\Services;

use App\Exceptions\InvalidDomainException;
use App\Models\Domain;
use App\Support\DomainNormaliser;
use Illuminate\Support\Facades\Schema;

class DomainLinker
{
    /**
     * Return the Domain row for a canonical name, creating it for the customer when missing.
     * Trashed rows are matched but never restored.
     *
     * @throws InvalidDomainException when the name cannot be canonicalised
     */
    public function findOrCreate(string $canonical, ?int $companyId): Domain
    {
        // Idempotent on canonical input; makes name variants map to one row.
        $name = DomainNormaliser::normalise($canonical);

        $existing = Domain::withTrashed()->where('domain_name', $name)->first();
        if ($existing !== null) {
            return $existing;
        }

        $domain = new Domain([
            'company_id' => $companyId,
            'domain_name' => $name,
            'registrar' => null,
        ]);

        if (Schema::hasColumn('domains', 'status')) {
            $domain->forceFill(['status' => 'active']);
        }

        $domain->save();

        return $domain;
    }
}
