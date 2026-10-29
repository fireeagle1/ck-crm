<?php

namespace App\Mcp\Dto;

use App\Models\CustomerApp;

/**
 * Data-minimisation transformer for the CustomerApp (Web App) domain.
 *
 * Exposes only the technical metadata fields the MCP connector is allowed to
 * read and write back: the customer-facing display name and install URL, the
 * three editable technical fields, plus the linking identifiers and light
 * context from the related service. Model attributes are never spread and
 * ->toArray() is never called, so nothing beyond this whitelist can leak
 * regardless of future model/$fillable changes.
 */
final class CustomerAppDto
{
    public static function fromModel(CustomerApp $app): array
    {
        return [
            'id' => $app->id,
            'service_id' => $app->service_id,
            'service_short' => $app->service?->service_short,
            'company_id' => $app->service?->company_id,
            'display_name' => $app->display_name,
            'install_url' => $app->install_url,
            'repo_url' => $app->repo_url,
            'hosting_location' => $app->hosting_location,
            'codebase_info' => $app->codebase_info,
            'updated_at' => self::date($app->updated_at),
        ];
    }

    private static function date($value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof \DateTimeInterface
            ? $value->format(\DateTimeInterface::ATOM)
            : (string) $value;
    }
}
