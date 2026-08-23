<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\InspectionReportPdfService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingInspectionReportController extends Controller
{
    public function __construct(
        protected InspectionReportPdfService $pdfService
    ) {}

    /**
     * Download the inspection report PDF for a booking.
     *
     * Requirements: 5.2, 5.4, 5.5
     */
    public function download(Booking $booking): StreamedResponse
    {
        abort_unless($booking->inspections()->exists(), 404);

        $pdf = $this->pdfService->generate($booking);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'inspection-report-booking-' . $booking->id . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }
}
