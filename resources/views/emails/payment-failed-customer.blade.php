@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
        Payment Issue with Your Order
    </h2>

    <p style="margin:0 0 12px; font-size:14px; color:#374151; line-height:1.6;">
        Hi {{ $order->customer?->company_name ?? 'Customer' }},
    </p>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        We were unable to process the payment for your recent order. Please see the details below.
    </p>

    <div style="background-color:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:16px; margin:0 0 16px;">
        <table style="width:100%; font-size:14px; color:#374151;">
            <tr>
                <td style="padding:4px 0; font-weight:600;">Order Reference</td>
                <td style="padding:4px 0;">#{{ $order->id }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Amount</td>
                <td style="padding:4px 0;">&pound;{{ number_format($order->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Issue</td>
                <td style="padding:4px 0; color:#dc2626;">{{ $reason }}</td>
            </tr>
        </table>
    </div>

    <h3 style="margin:16px 0 8px; font-size:15px; font-weight:600; color:#111827;">
        What to do next
    </h3>

    <ul style="margin:0 0 16px; padding-left:20px; font-size:14px; color:#374151; line-height:1.8;">
        <li>Check that your card details are correct and up to date</li>
        <li>Ensure sufficient funds are available on your payment method</li>
        <li>Try again using a different payment method if the issue persists</li>
    </ul>

    <p style="margin:0; font-size:13px; color:#6b7280; line-height:1.5;">
        If you continue to experience issues, please contact us or open a support ticket and we will be happy to help.
    </p>
@endsection
