<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Channels\ApnChannel;
use Illuminate\Notifications\Notification;

class InvoiceOverdueNotification extends Notification
{
    public function __construct(private Invoice $invoice)
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [ApnChannel::class];
    }

    /**
     * Get the APNs payload for the notification.
     *
     * @return array<string, mixed>
     */
    public function toApn(object $notifiable): array
    {
        $customerName = $this->invoice->customer?->company_name ?? 'Unknown Customer';
        $amount = number_format((float) $this->invoice->invoice_amount, 2);

        return [
            'alert' => [
                'title' => 'Invoice Overdue',
                'body' => "£{$amount} — {$customerName}",
            ],
            'badge' => 1,
            'sound' => 'default',
        ];
    }
}
