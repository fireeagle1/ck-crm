<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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

        // Generate QR code as base64 PNG for embedding in the PDF
        $qrCode = $this->generateQrCode($booking);

        // Load logo as base64 for embedding
        $logoBase64 = $this->getLogoBase64();

        $data = [
            'booking' => $booking,
            'customer' => $booking->customer,
            'order' => $booking->orderItem?->order,
            'companyName' => Setting::get('company_name', config('app.name', 'Company')),
            'companyAddress' => Setting::get('company_address', ''),
            'companyPhone' => Setting::get('company_phone', ''),
            'companyEmail' => Setting::get('company_email', ''),
            'deliveryInstructions' => $booking->product?->delivery_instructions,
            'rentalAgreementText' => $booking->product?->rental_agreement_text,
            'qrCodeBase64' => $qrCode,
            'logoBase64' => $logoBase64,
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

    /**
     * Generate a QR code containing the booking reference (BKG-{id}) as a base64 PNG.
     */
    private function generateQrCode(Booking $booking): ?string
    {
        try {
            $qrContent = 'BKG-' . $booking->id;

            $qrSvg = QrCode::format('svg')
                ->size(200)
                ->margin(1)
                ->errorCorrection('H')
                ->generate($qrContent);

            return 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
        } catch (\Exception $e) {
            Log::warning('BookingConfirmationPdfService: QR code generation failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Load the company logo from public storage as a base64-encoded data URI.
     */
    private function getLogoBase64(): ?string
    {
        try {
            $logoPath = 'branding/2BsuZm9JT0K0Or0e1Y2jZx3NsO2lKAecCYFH4Y7m.png';

            if (!Storage::disk('public')->exists($logoPath)) {
                return null;
            }

            $contents = Storage::disk('public')->get($logoPath);
            $mimeType = Storage::disk('public')->mimeType($logoPath) ?: 'image/png';

            return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
        } catch (\Exception $e) {
            Log::warning('BookingConfirmationPdfService: Logo loading failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
