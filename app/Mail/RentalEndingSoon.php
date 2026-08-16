<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RentalEndingSoon extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reminder: Your rental of {$this->booking->product?->name} ends tomorrow",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.rental-ending-soon',
            with: [
                'booking' => $this->booking,
            ],
        );
    }
}
