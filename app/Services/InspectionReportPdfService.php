<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingInspection;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InspectionReportPdfService
{
    /**
     * Generate a return inspection report PDF and store it.
     *
     * Returns the relative storage path on success, null on failure.
     * The PDF includes inspection photos, condition notes, damage status,
     * and booking/customer details.
     */
    public function generate(Booking $booking, BookingInspection $inspection): ?string
    {
        $booking->loadMissing(['product', 'customer', 'orderItem.order']);
        $inspection->loadMissing('inspector');

        // Convert stored photo paths to base64-encoded data URIs for embedding in the PDF
        $photoDataUris = $this->buildPhotoDataUris($inspection->photos ?? []);

        // Load logo as base64 for embedding
        $logoBase64 = $this->getLogoBase64();

        $data = [
            'booking' => $booking,
            'inspection' => $inspection,
            'customer' => $booking->customer,
            'order' => $booking->orderItem?->order,
            'product' => $booking->product,
            'inspector' => $inspection->inspector,
            'photos' => $photoDataUris,
            'companyName' => Setting::get('company_name', config('app.name', 'Company')),
            'companyAddress' => Setting::get('company_address', ''),
            'companyPhone' => Setting::get('company_phone', ''),
            'companyEmail' => Setting::get('company_email', ''),
            'logoBase64' => $logoBase64,
        ];

        try {
            $pdf = Pdf::loadView('pdf.inspection-report', $data);
            $pdf->setPaper('A4', 'portrait');

            $relativePath = "inspections/report-{$booking->id}-return.pdf";

            $pdfContent = $pdf->output();
            Storage::disk('local')->put($relativePath, $pdfContent);

            if (!Storage::disk('local')->exists($relativePath)) {
                Log::error('InspectionReportPdfService: PDF file not found after save', [
                    'booking_id' => $booking->id,
                    'inspection_id' => $inspection->id,
                    'path' => $relativePath,
                ]);

                return null;
            }

            return $relativePath;
        } catch (\Exception $e) {
            Log::error('InspectionReportPdfService: Failed to generate PDF', [
                'booking_id' => $booking->id,
                'inspection_id' => $inspection->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Convert stored photo paths to base64 data URIs for PDF embedding.
     *
     * @param array $photoPaths
     * @return array<array{uri: string|null, index: int}>
     */
    private function buildPhotoDataUris(array $photoPaths): array
    {
        $results = [];

        foreach ($photoPaths as $index => $path) {
            $uri = null;

            try {
                if (Storage::disk('local')->exists($path)) {
                    $contents = Storage::disk('local')->get($path);
                    $extension = pathinfo($path, PATHINFO_EXTENSION);
                    $mime = $extension === 'png' ? 'image/png' : 'image/jpeg';
                    $uri = "data:{$mime};base64," . base64_encode($contents);
                }
            } catch (\Exception $e) {
                Log::warning('InspectionReportPdfService: Failed to read photo', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }

            $results[] = [
                'uri' => $uri,
                'index' => $index + 1,
            ];
        }

        return $results;
    }

    /**
     * Load the company logo from public storage as a base64-encoded data URI.
     */
    private function getLogoBase64(): ?string
    {
        try {
            $logoPath = 'branding/2BsuZm9JT0K0Or0e1Y2jZx3NsO2lKAecCYFH4Y7m.png';
            $absolutePath = storage_path('app/public/' . $logoPath);

            if (!file_exists($absolutePath)) {
                return null;
            }

            $contents = file_get_contents($absolutePath);
            if ($contents === false) {
                return null;
            }

            return 'data:image/png;base64,' . base64_encode($contents);
        } catch (\Exception $e) {
            Log::warning('InspectionReportPdfService: Logo loading failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
