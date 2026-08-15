@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#dc2626;">
        Payment Failed
    </h2>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        A payment has failed for a customer order. Please review and take appropriate action.
    </p>

    <div style="background-color:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:16px; margin:0 0 16px;">
        <table style="width:100%; font-size:14px; color:#374151;">
            <tr>
                <td style="padding:4px 0; font-weight:600;">Order ID</td>
                <td style="padding:4px 0;">#{{ $order->id }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Customer</td>
                <td style="padding:4px 0;">{{ $order->customer?->company_name ?? 'Unknown' }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Amount</td>
                <td style="padding:4px 0;">&pound;{{ number_format($order->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Failure Reason</td>
                <td style="padding:4px 0; color:#dc2626;">{{ $reason }}</td>
            </tr>
        </table>
    </div>

    <p style="margin:0; font-size:13px; color:#6b7280; line-height:1.5;">
        You may need to contact the customer to arrange an alternative payment method or resolve the issue.
    </p>
@endsection
