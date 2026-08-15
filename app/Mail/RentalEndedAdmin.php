<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RentalEndedAdmin extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Booking $booking,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $productName = $this->booking->product?->name ?? 'Unknown Product';

        return new Envelope(
            subject: "Rental Period Ended - {$productName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.rental-ended-admin',
            with: [
                'booking' => $this->booking,
            ],
        );
    }
}
