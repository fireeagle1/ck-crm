<?php

namespace App\Mcp\Dto;

use App\Models\Service;

/**
 * Data-minimisation transformer for the Service domain.
 *
 * Never exposes cpanel_username or stripe_subscription_id. Model attributes
 * are never spread and ->toArray() is never called, so these secrets are
 * structurally absent regardless of future model/$fillable changes.
 */
final class ServiceDto
{
    public static function fromModel(Service $service): array
    {
        return [
            'service_id' => $service->service_id,
            'company_id' => $service->company_id,
            'service_short' => $service->service_short,
            'service_type' => $service->service_type,
            'status' => $service->status,
            'start_date' => self::date($service->start_date),
            'end_date' => self::date($service->end_date),
        ];
    }

    private static function date($value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : (string) $value;
    }
}
