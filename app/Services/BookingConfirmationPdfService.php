<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BookingConfirmationPdfService
{
    /**
     * Generate a booking confirmation PDF and store it.
     *
     * Returns the relative storage path on success, null on failure.
     * Updates the booking's confirmation_pdf_path field.
     */
    public function generate(Booking $booking): ?string
    {
        $booking->loadMissing(['product', 'customer', 'orderItem.order']);

        $data = [
            'booking' => $booking,
            'customer' => $booking->customer,
            'order' => $booking->orderItem?->order,
            'companyName' => Setting::get('company_name', config('app.name', 'Company')),
            'companyAddress' => Setting::get('company_address', ''),
            'companyPhone' => Setting::get('company_phone', ''),
            'companyEmail' => Setting::get('company_email', ''),
            'deliveryInstructions' => $booking->product?->delivery_instructions,
        ];

        try {
            $pdf = Pdf::loadView('pdf.booking-confirmation', $data);
            $pdf->setPaper('A4', 'portrait');

            $relativePath = 'bookings/confirmation-' . $booking->id . '.pdf';

            // Use Storage facade to write to the correct disk location
            $pdfContent = $pdf->output();
            Storage::disk('local')->put($relativePath, $pdfContent);

            // Verify the file was actually written
            if (!Storage::disk('local')->exists($relativePath)) {
                Log::error('BookingConfirmationPdfService: PDF file not found after save', [
                    'booking_id' => $booking->id,
                    'path' => $relativePath,
                ]);

                return null;
            }

            // Store the path on the booking
            $booking->updateQuietly(['confirmation_pdf_path' => $relativePath]);

            return $relativePath;
        } catch (\Exception $e) {
            Log::error('BookingConfirmationPdfService: Failed to generate PDF', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
