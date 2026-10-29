<?php

namespace App\Mcp\Dto;

use App\Models\Invoice;

/**
 * Data-minimisation transformer for the Invoice domain.
 *
 * Exposes the non-secret hosted-invoice reference (stripe_hosted_url) while
 * excluding the internal Stripe identifier (stripe_invoice_id). Model
 * attributes are never spread and ->toArray() is never called.
 */
final class InvoiceDto
{
    public static function fromModel(Invoice $invoice): array
    {
        return [
            'invoice_id' => $invoice->invoice_id,
            'company_id' => $invoice->company_id,
            'invoice_status' => $invoice->invoice_status,
            'invoice_amount' => $invoice->invoice_amount,
            'invoice_date' => self::date($invoice->invoice_date),
            'due_date' => self::date($invoice->due_date),
            'paid_date' => self::date($invoice->paid_date),
            'stripe_hosted_url' => $invoice->stripe_hosted_url,
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
