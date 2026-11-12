<?php

namespace App\Services;

use App\Models\CustomerApp;
use App\Models\Domain;
use App\Models\Service;
use App\Support\DomainNormaliser;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates the single web app (CustomerApp) allowed per service.
 *
 * The DB unique index on customer_apps.service_id is the source of truth, so a
 * racing second create fails the same way as a sequential one (Req 16.3, 16.4).
 */
class CustomerAppService
{
    public const DUPLICATE_MESSAGE = 'This service already has a linked app.';

    /**
     * @param  array<string, mixed>  $data  display_name, install_url, repo_url, hosting_location, codebase_info
     *
     * @throws ValidationException when the service already has an app
     */
    public function createForService(Service $service, array $data, ?Domain $domain = null): CustomerApp
    {
        $domain ??= $service->domain_id !== null
            ? Domain::withTrashed()->find($service->domain_id)
            : null;

        $installUrl = $data['install_url'] ?? null;
        if (($installUrl === null || $installUrl === '') && $domain !== null) {
            $canonical = DomainNormaliser::tryNormalise($domain->domain_name) ?? $domain->domain_name;
            $installUrl = DomainNormaliser::toHttpsUrl($canonical);
        }

        try {
            return DB::transaction(fn () => CustomerApp::create([
                'service_id' => $service->service_id,
                'domain_id' => $domain?->id,
                'display_name' => $data['display_name'] ?? null,
                'install_url' => $installUrl === '' ? null : $installUrl,
                'repo_url' => $data['repo_url'] ?? null,
                'hosting_location' => $data['hosting_location'] ?? null,
                'codebase_info' => $data['codebase_info'] ?? null,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['service_id' => self::DUPLICATE_MESSAGE]);
        }
    }
}
