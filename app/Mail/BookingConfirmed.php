<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Booking Confirmed — {$this->booking->product?->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-confirmed',
            with: [
                'booking' => $this->booking,
            ],
        );
    }

    /**
     * Attach the booking confirmation PDF if available.
     */
    public function attachments(): array
    {
        $attachments = [];

        if ($this->booking->confirmation_pdf_path
            && file_exists(storage_path('app/' . $this->booking->confirmation_pdf_path))) {
            $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromPath(
                storage_path('app/' . $this->booking->confirmation_pdf_path)
            )->as('booking-confirmation-' . $this->booking->id . '.pdf')
             ->withMime('application/pdf');
        }

        return $attachments;
    }
}
