<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeadConfirmation extends Mailable implements ShouldQueue
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
        return new Envelope(
            subject: 'Thanks for getting in touch with CK Enterprises',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lead-confirmation',
            with: [
                'lead' => $this->lead,
            ],
        );
    }
}
