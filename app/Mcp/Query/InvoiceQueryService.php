<?php

namespace App\Mcp\Query;

use App\Mcp\Dto\InvoiceDto;
use App\Mcp\Support\Pagination;
use App\Models\Invoice;

/**
 * Read query service backing the Invoice MCP read tools (invoices:read).
 *
 * Builds queries over the Invoice model, applies optional company_id and
 * invoice_status filters, defaults to the collectable set (excludes
 * Void/Uncollectible/Deleted via Invoice::scopeCollectable, matching the admin
 * API financial queries), caps the page size with the shared Pagination
 * helper, and maps every row through InvoiceDto so no secret Stripe identifier
 * or other sensitive attribute can leak. Full models are never serialised.
 *
 * @see \App\Models\Invoice::scopeCollectable()
 * @see \App\Mcp\Dto\InvoiceDto
 * @see \App\Mcp\Support\Pagination::effectivePerPage()
 */
final class InvoiceQueryService
{
    /**
     * Fetch a single invoice by its primary key (invoice_id).
     *
     * Returns the DTO array for the matching invoice, or null when no invoice
     * exists for the given id. The collectable default is NOT applied to a
     * direct lookup so that a specific invoice can still be inspected
     * regardless of status.
     *
     * @return array<string, mixed>|null
     */
    public function getInvoice(int $invoiceId): ?array
    {
        $invoice = Invoice::query()
            ->whereKey($invoiceId)
            ->first();

        return $invoice === null ? null : InvoiceDto::fromModel($invoice);
    }

    /**
     * List invoices, optionally filtered by company and/or status.
     *
     * Defaults to the collectable set (excludes Void/Uncollectible/Deleted) to
     * match the admin API. Supplying an explicit invoice_status filters within
     * the collectable set. Results are ordered newest-first by invoice_date and
     * the page size is capped via Pagination::effectivePerPage().
     *
     * @return array{
     *     data: array<int, array<string, mixed>>,
     *     page: int,
     *     per_page: int,
     *     total: int,
     *     last_page: int
     * }
     */
    public function listInvoices(
        ?int $companyId = null,
        ?string $invoiceStatus = null,
        ?int $page = null,
        ?int $perPage = null,
    ): array {
        $query = Invoice::query()->collectable();

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        if ($invoiceStatus !== null && $invoiceStatus !== '') {
            $query->where('invoice_status', $invoiceStatus);
        }

        $effectivePerPage = Pagination::effectivePerPage($perPage);
        $currentPage = ($page !== null && $page > 0) ? $page : 1;

        $paginator = $query
            ->orderByDesc('invoice_date')
            ->orderByDesc('invoice_id')
            ->paginate($effectivePerPage, ['*'], 'page', $currentPage);

        return [
            'data' => $paginator->getCollection()
                ->map(static fn (Invoice $invoice): array => InvoiceDto::fromModel($invoice))
                ->all(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
