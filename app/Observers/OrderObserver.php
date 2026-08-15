<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\NotificationService;
use App\Services\PdfInvoiceService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "updated" event.
     *
     * When payment_status changes to 'paid' or 'paid_offline', generate
     * a PDF invoice and send an order confirmation email.
     *
     * Wrapped in try/catch so that PDF generation or notification failure
     * does not break the order flow.
     *
     * Requirements: 17.1, 18.3, 21.1
     */
    public function updated(Order $order): void
    {
        // Only trigger on payment_status change
        if (!$order->wasChanged('payment_status')) {
            return;
        }

        // Only trigger when changed to 'paid' or 'paid_offline'
        if (!in_array($order->payment_status, ['paid', 'paid_offline'])) {
            return;
        }

        try {
            $pdfPath = app(PdfInvoiceService::class)->generate($order);

            if ($pdfPath) {
                $order->updateQuietly(['invoice_pdf_path' => $pdfPath]);
            }
        } catch (\Exception $e) {
            Log::error('OrderObserver: Failed to generate PDF invoice', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            app(NotificationService::class)->sendOrderConfirmation($order);
        } catch (\Exception $e) {
            Log::error('OrderObserver: Failed to send order confirmation', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
