<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HostingDomainConflictAdmin extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $domain,
        public ?int $existingServiceId = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Order #{$this->order->id} held: domain {$this->domain} already in use",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hosting-domain-conflict-admin',
            with: [
                'order' => $this->order,
                'domain' => $this->domain,
                'existingServiceId' => $this->existingServiceId,
            ],
        );
    }
}
