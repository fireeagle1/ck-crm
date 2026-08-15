@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
        Equipment Return Confirmed
    </h2>

    <p style="margin:0 0 12px; font-size:14px; color:#374151; line-height:1.6;">
        Hi {{ $booking->customer?->company_name ?? 'Customer' }},
    </p>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        We are writing to confirm that we have received and processed the return of your rented equipment.
    </p>

    <div style="background-color:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:16px; margin:0 0 16px;">
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
                <td style="padding:4px 0;">{{ $booking->returned_at?->format('M j, Y') }}</td>
            </tr>
        </table>
    </div>

    <p style="margin:0; font-size:13px; color:#6b7280; line-height:1.5;">
        Thank you for renting with us. If you have any questions, please don't hesitate to get in touch.
    </p>
@endsection
