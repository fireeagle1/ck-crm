<?php

namespace App\Mcp\Dto;

use App\Models\Ticket;

/**
 * Data-minimisation transformer for a full ticket detail view.
 *
 * Includes the description, compact customer/service/asset references, the
 * complete reply thread (both internal and non-internal replies), and the
 * activity history. Related rows are mapped through their own allow-listed
 * DTOs so no security field or third-party secret (e.g. a service's
 * cpanel_username / stripe_subscription_id, or any user row) can leak.
 * Model attributes are never spread.
 */
final class TicketDetailDto
{
    public static function fromModel(Ticket $ticket): array
    {
        return [
            'ticket_id' => $ticket->ticket_id,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'ticket_type' => $ticket->ticket_type,
            'request_category' => $ticket->request_category,
            'company_id' => $ticket->company_id,
            'customer' => self::customerReference($ticket),
            'service' => self::serviceReference($ticket),
            'asset' => self::assetReference($ticket),
            'first_replied_at' => self::datetime($ticket->first_replied_at),
            'due_at' => self::datetime($ticket->due_at),
            'created_at' => self::datetime($ticket->created_at),
            'replies' => $ticket->replies
                ->map(static fn ($reply) => TicketReplyDto::fromModel($reply))
                ->all(),
            'activities' => $ticket->activities
                ->map(static fn ($activity) => TicketActivityDto::fromModel($activity))
                ->all(),
        ];
    }

    private static function customerReference(Ticket $ticket): ?array
    {
        $customer = $ticket->customer;
        if ($customer === null) {
            return null;
        }

        return [
            'company_id' => $customer->company_id,
            'company_name' => $customer->company_name,
            'customer_name' => $customer->customer_name,
        ];
    }

    private static function serviceReference(Ticket $ticket): ?array
    {
        $service = $ticket->service;
        if ($service === null) {
            return null;
        }

        return [
            'service_id' => $service->service_id,
            'service_short' => $service->service_short,
            'service_type' => $service->service_type,
        ];
    }

    private static function assetReference(Ticket $ticket): ?array
    {
        $asset = $ticket->asset;
        if ($asset === null) {
            return null;
        }

        return [
            'device_id' => $asset->device_id,
            'device_name' => $asset->device_name,
        ];
    }

    private static function datetime($value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof \DateTimeInterface
            ? $value->format(\DateTimeInterface::ATOM)
            : (string) $value;
    }
}
