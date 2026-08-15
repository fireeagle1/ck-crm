@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
        Rental Period Ended
    </h2>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        A rental booking has reached its end date. The equipment is due for return.
    </p>

    <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:16px; margin:0 0 16px;">
        <table style="width:100%; font-size:14px; color:#374151;">
            <tr>
                <td style="padding:4px 0; font-weight:600;">Product</td>
                <td style="padding:4px 0;">{{ $booking->product?->name ?? 'Unknown Product' }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Customer</td>
                <td style="padding:4px 0;">{{ $booking->customer?->company_name ?? 'Unknown' }}</td>
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
                <td style="padding:4px 0; font-weight:600;">Booking ID</td>
                <td style="padding:4px 0;">#{{ $booking->id }}</td>
            </tr>
        </table>
    </div>

    <p style="margin:0; font-size:13px; color:#6b7280; line-height:1.5;">
        Please arrange collection or confirm the customer has returned the equipment, then mark the booking as returned in the admin panel.
    </p>
@endsection
