<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PdfInvoiceService
{
    /**
     * Generate a PDF invoice for the given order and store at storage path.
     *
     * Uses DomPDF to render a Blade template with company details, customer info,
     * line items, totals, and payment reference. Stores the generated PDF in
     * storage/app/invoices/{order_id}.pdf and updates the order's invoice_pdf_path.
     *
     * Returns the relative storage path on success, or null on failure.
     * Does not block fulfilment on failure (logs the error and returns null).
     *
     * Requirements: 17.1, 17.2, 17.3, 17.4
     */
    public function generate(Order $order): ?string
    {
        try {
            $order->loadMissing(['items', 'customer']);

            $data = $this->buildInvoiceData($order);

            $pdf = Pdf::loadView('pdf.invoice', $data);
            $pdf->setPaper('A4', 'portrait');

            $relativePath = 'invoices/' . $order->id . '.pdf';
            $fullPath = storage_path('app/' . $relativePath);

            // Ensure the invoices directory exists
            $directory = dirname($fullPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $pdf->save($fullPath);

            // Update the order record with the PDF path (use updateQuietly to avoid re-triggering observers)
            $order->updateQuietly(['invoice_pdf_path' => $relativePath]);

            return $relativePath;
        } catch (\Exception $e) {
            Log::error('PdfInvoiceService: Failed to generate invoice PDF', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Build the data array for the invoice Blade template.
     */
    protected function buildInvoiceData(Order $order): array
    {
        $companyName = Setting::get('company_name', config('app.name', 'Company'));
        $companyAddress = Setting::get('company_address', '');
        $companyLogo = Setting::get('company_logo', '');
        $companyPhone = Setting::get('company_phone', '');
        $companyEmail = Setting::get('company_email', '');

        $customer = $order->customer;
        $items = $order->items;

        // Calculate subtotal from line items
        $subtotal = $items->sum(function ($item) {
            return $item->price * ($item->quantity ?? 1);
        });

        // VAT calculation (20% UK standard rate)
        $vatRate = (float) Setting::get('vat_rate', 20);
        $vatAmount = round($subtotal * ($vatRate / 100), 2);
        $total = $subtotal + $vatAmount;

        return [
            'order' => $order,
            'items' => $items,
            'customer' => $customer,
            'companyName' => $companyName,
            'companyAddress' => $companyAddress,
            'companyLogo' => $companyLogo,
            'companyPhone' => $companyPhone,
            'companyEmail' => $companyEmail,
            'subtotal' => $subtotal,
            'vatRate' => $vatRate,
            'vatAmount' => $vatAmount,
            'total' => $total,
            'paymentReference' => $order->stripe_payment_intent_id ?? $order->stripe_checkout_session_id ?? 'N/A',
            'paymentStatus' => ucfirst(str_replace('_', ' ', $order->payment_status ?? 'unknown')),
            'orderDate' => $order->created_at?->format('d/m/Y') ?? now()->format('d/m/Y'),
            'deliveryAddress' => $this->formatDeliveryAddress($order),
        ];
    }

    /**
     * Format the delivery address from the order, if present.
     */
    protected function formatDeliveryAddress(Order $order): ?string
    {
        $parts = array_filter([
            $order->delivery_address_line1,
            $order->delivery_address_line2,
            $order->delivery_city,
            $order->delivery_state,
            $order->delivery_postal_code,
            $order->delivery_country,
        ]);

        return !empty($parts) ? implode(', ', $parts) : null;
    }
}
