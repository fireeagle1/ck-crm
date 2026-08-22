@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
        Return Inspection Report
    </h2>

    <p style="margin:0 0 12px; font-size:14px; color:#374151; line-height:1.6;">
        Hi {{ $booking->customer?->company_name ?? 'Customer' }},
    </p>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        Your equipment return has been inspected and processed. Please find the full inspection report attached to this email as a PDF.
    </p>

    {{-- Summary Box --}}
    <div style="background-color:#f5f3ff; border:1px solid #e5e7eb; border-radius:6px; padding:16px; margin:0 0 16px;">
        <table style="width:100%; font-size:14px; color:#374151;">
            <tr>
                <td style="padding:4px 0; font-weight:600;">Product</td>
                <td style="padding:4px 0;">{{ $booking->product?->name ?? 'Equipment' }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Quantity</td>
                <td style="padding:4px 0;">{{ $booking->quantity }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Rental Period</td>
                <td style="padding:4px 0;">{{ $booking->start_date?->format('M j') }} &ndash; {{ $booking->end_date?->format('M j, Y') }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Returned On</td>
                <td style="padding:4px 0;">{{ $booking->returned_at?->format('M j, Y') ?? $inspection->inspected_at?->format('M j, Y') }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Inspected</td>
                <td style="padding:4px 0;">{{ $inspection->inspected_at?->format('M j, Y \a\t H:i') }}</td>
            </tr>
        </table>
    </div>

    {{-- Condition Status --}}
    @if($inspection->damage_flagged)
        <div style="background-color:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:12px 16px; margin:0 0 16px;">
            <strong style="color:#991b1b; font-size:14px;">Damage Reported</strong>
            <p style="margin:6px 0 0; font-size:13px; color:#7f1d1d; line-height:1.5;">
                Damage was flagged during the return inspection. Please refer to the attached report for full details and photos.
            </p>
        </div>
    @else
        <div style="background-color:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:12px 16px; margin:0 0 16px;">
            <strong style="color:#166534; font-size:14px;">Good Condition</strong>
            <p style="margin:6px 0 0; font-size:13px; color:#14532d; line-height:1.5;">
                No damage was reported. The equipment has been returned in satisfactory condition.
            </p>
        </div>
    @endif

    @if($inspection->condition_notes)
        <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:12px 16px; margin:0 0 16px;">
            <strong style="font-size:13px; color:#374151;">Condition Notes:</strong>
            <p style="margin:6px 0 0; font-size:13px; color:#4b5563; line-height:1.5;">
                {{ $inspection->condition_notes }}
            </p>
        </div>
    @endif

    <p style="margin:0; font-size:13px; color:#6b7280; line-height:1.5;">
        The full inspection report including photographs is attached as a PDF. Thank you for renting with us. If you have any questions, please don't hesitate to get in touch.
    </p>
@endsection
