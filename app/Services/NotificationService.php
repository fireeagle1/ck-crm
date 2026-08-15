<?php

namespace App\Services;

use App\Mail\AdminNewOrder;
use App\Mail\HostingProvisioned;
use App\Mail\LowStockAdmin;
use App\Mail\OrderConfirmation;
use App\Mail\PaymentFailedAdmin;
use App\Mail\PaymentFailedCustomer;
use App\Mail\RentalEndedAdmin;
use App\Mail\ReturnConfirmed;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Send new order notification to admin.
     *
     * Dispatches a queued email to all admin users containing order details,
     * customer name, and product information.
     *
     * Requirement 7.1
     */
    public function notifyAdminNewOrder(Order $order): void
    {
        $admins = $this->getAdminEmails();

        if (empty($admins)) {
            Log::warning('NotificationService: No admin emails found for new order notification', [
                'order_id' => $order->id,
            ]);
            return;
        }

        try {
            Mail::to($admins)->queue(new AdminNewOrder($order));
        } catch (\Exception $e) {
            Log::error('NotificationService: Failed to dispatch admin new order notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send rental end reminder to admin.
     *
     * Dispatches a queued email to all admin users indicating the rental period
     * has ended and equipment is due for return.
     *
     * Requirement 7.2
     */
    public function notifyAdminRentalEnded(Booking $booking): void
    {
        $admins = $this->getAdminEmails();

        if (empty($admins)) {
            Log::warning('NotificationService: No admin emails found for rental ended notification', [
                'booking_id' => $booking->id,
            ]);
            return;
        }

        try {
            Mail::to($admins)->queue(new RentalEndedAdmin($booking));
        } catch (\Exception $e) {
            Log::error('NotificationService: Failed to dispatch rental ended notification', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send return confirmation to customer.
     *
     * Dispatches a queued email to the customer confirming the rental
     * item has been marked as returned.
     *
     * Requirement 7.3
     */
    public function notifyCustomerReturnConfirmed(Booking $booking): void
    {
        $customerEmail = $booking->customer?->users()->first()?->email;

        if (!$customerEmail) {
            Log::warning('NotificationService: No customer email found for return confirmation', [
                'booking_id' => $booking->id,
                'company_id' => $booking->company_id,
            ]);
            return;
        }

        try {
            Mail::to($customerEmail)->queue(new ReturnConfirmed($booking));
        } catch (\Exception $e) {
            Log::error('NotificationService: Failed to dispatch return confirmation', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send low-stock alert to admin (once per threshold breach).
     *
     * Dispatches a queued email to all admin users and sets the
     * low_stock_notified flag to true to prevent duplicate alerts.
     *
     * Requirement 7.4
     */
    public function notifyAdminLowStock(Product $product): void
    {
        $admins = $this->getAdminEmails();

        if (empty($admins)) {
            Log::warning('NotificationService: No admin emails found for low stock notification', [
                'product_id' => $product->id,
            ]);
            return;
        }

        try {
            Mail::to($admins)->queue(new LowStockAdmin($product));

            $product->update(['low_stock_notified' => true]);
        } catch (\Exception $e) {
            Log::error('NotificationService: Failed to dispatch low stock notification', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send payment failure notification to admin AND customer.
     *
     * Dispatches queued emails to both admin users and the customer
     * containing order reference, failure reason, and instructions.
     *
     * Requirements 8.1, 8.2
     */
    public function notifyPaymentFailure(Order $order, string $failureReason): void
    {
        // Notify admin
        $admins = $this->getAdminEmails();

        if (!empty($admins)) {
            try {
                Mail::to($admins)->queue(new PaymentFailedAdmin($order, $failureReason));
            } catch (\Exception $e) {
                Log::error('NotificationService: Failed to dispatch payment failure admin notification', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Notify customer
        $customerEmail = $order->customer?->users()->first()?->email;

        if ($customerEmail) {
            try {
                Mail::to($customerEmail)->queue(new PaymentFailedCustomer($order, $failureReason));
            } catch (\Exception $e) {
                Log::error('NotificationService: Failed to dispatch payment failure customer notification', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::warning('NotificationService: No customer email found for payment failure notification', [
                'order_id' => $order->id,
                'company_id' => $order->company_id,
            ]);
        }
    }

    /**
     * Send hosting provisioned email to customer.
     *
     * Dispatches a queued email containing nameserver instructions,
     * cPanel username, and login details after hosting account creation.
     *
     * Requirement 3.6
     */
    public function notifyCustomerHostingProvisioned(Service $service): void
    {
        $customerEmail = $service->customer?->users()->first()?->email;

        if (!$customerEmail) {
            Log::warning('NotificationService: No customer email found for hosting provisioned notification', [
                'service_id' => $service->service_id,
                'company_id' => $service->company_id,
            ]);
            return;
        }

        $nameservers = [
            Setting::get('whm_nameserver_0', 'ns0.thundercloud.uk'),
            Setting::get('whm_nameserver_1', 'ns1.thundercloud.uk'),
        ];

        try {
            Mail::to($customerEmail)->queue(new HostingProvisioned($service, $nameservers));
        } catch (\Exception $e) {
            Log::error('NotificationService: Failed to dispatch hosting provisioned notification', [
                'service_id' => $service->service_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send order confirmation with optional PDF attachment.
     *
     * Dispatches a queued email to the customer with line items,
     * delivery instructions for applicable items, and attaches
     * the PDF invoice if one has been generated.
     *
     * Requirements 18.3, 21.1, 21.2
     */
    public function sendOrderConfirmation(Order $order): void
    {
        $customerEmail = $order->customer?->users()->first()?->email;

        if (!$customerEmail) {
            Log::warning('NotificationService: No customer email found for order confirmation', [
                'order_id' => $order->id,
                'company_id' => $order->company_id,
            ]);
            return;
        }

        try {
            $mailable = new OrderConfirmation($order);

            // Attach PDF invoice if available
            if ($order->invoice_pdf_path && file_exists(storage_path('app/' . $order->invoice_pdf_path))) {
                $mailable->attach(storage_path('app/' . $order->invoice_pdf_path), [
                    'as' => 'invoice-' . $order->id . '.pdf',
                    'mime' => 'application/pdf',
                ]);
            }

            Mail::to($customerEmail)->queue($mailable);
        } catch (\Exception $e) {
            Log::error('NotificationService: Failed to dispatch order confirmation', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get all admin user email addresses.
     *
     * @return array<string>
     */
    protected function getAdminEmails(): array
    {
        return User::where('is_admin', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->toArray();
    }
}
