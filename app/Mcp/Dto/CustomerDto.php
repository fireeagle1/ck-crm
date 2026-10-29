<?php

namespace App\Mcp\Dto;

use App\Models\Customer;

/**
 * Data-minimisation transformer for the Customer domain.
 *
 * Returns a fixed associative array built from an explicit field allow-list.
 * Model attributes are never spread and ->toArray() is never called, so
 * security fields and third-party secrets (e.g. stripe_customer_id) are
 * structurally absent from the output. The users() relation is never
 * traversed, so no user security field can leak through this surface.
 */
final class CustomerDto
{
    public static function fromModel(Customer $customer): array
    {
        return [
            'company_id' => $customer->company_id,
            'company_name' => $customer->company_name,
            'customer_name' => $customer->customer_name,
            'phone_number' => $customer->phone_number,
            'address_line1' => $customer->address_line1,
            'address_line2' => $customer->address_line2,
            'city' => $customer->city,
            'state' => $customer->state,
            'postal_code' => $customer->postal_code,
            'country' => $customer->country,
        ];
    }
}
