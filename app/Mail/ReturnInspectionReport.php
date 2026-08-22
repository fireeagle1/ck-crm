<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\BookingInspection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReturnInspectionReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Booking $booking,
        public BookingInspection $inspection,
        public string $pdfPath,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Return Inspection Report — BKG-' . $this->booking->id,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.return-inspection-report',
            with: [
                'booking' => $this->booking,
                'inspection' => $this->inspection,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $absolutePath = storage_path('app/' . $this->pdfPath);

        if (!file_exists($absolutePath)) {
            return [];
        }

        return [
            Attachment::fromPath($absolutePath)
                ->as('inspection-report-BKG-' . $this->booking->id . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
