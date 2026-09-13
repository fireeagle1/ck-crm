<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewLead extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $lead
     */
    public function __construct(
        public array $lead,
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->lead['organisation'] ?? $this->lead['name'] ?? 'New enquiry';

        return new Envelope(
            subject: "New website enquiry from {$name}",
            replyTo: isset($this->lead['email'])
                ? [$this->lead['email']]
                : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-new-lead',
            with: [
                'lead' => $this->lead,
            ],
        );
    }
}
