<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Notifications\Channels\ApnChannel;
use Illuminate\Notifications\Notification;

class NewTicketNotification extends Notification
{
    public function __construct(private Ticket $ticket)
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
        $customerName = $this->ticket->customer?->company_name ?? 'Unknown Customer';

        return [
            'alert' => [
                'title' => 'New Ticket',
                'body' => "{$this->ticket->subject} — {$customerName}",
            ],
            'badge' => 1,
            'sound' => 'default',
        ];
    }
}
