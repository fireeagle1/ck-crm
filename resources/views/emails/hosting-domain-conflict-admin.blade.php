@extends('emails.layout')
@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#dc2626;">
        Hosting Order Held
    </h2>
    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        A paid hosting order could not create its service because the domain is already held by another service.
    </p>
    <div style="background-color:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:16px; margin:0 0 16px;">
        <table style="width:100%; font-size:14px; color:#374151;">
            <tr>
                <td style="padding:4px 0; font-weight:600;">Order ID</td>
                <td style="padding:4px 0;">#{{ $order->id }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Domain</td>
                <td style="padding:4px 0;">{{ $domain }}</td>
            </tr>
            @if ($existingServiceId)
                <tr>
                    <td style="padding:4px 0; font-weight:600;">Existing Service</td>
                    <td style="padding:4px 0;">#{{ $existingServiceId }}</td>
                </tr>
            @endif
        </table>
    </div>
    <p style="margin:0; font-size:13px; color:#6b7280; line-height:1.5;">
        The order is on hold. Contact the customer to resolve the domain before fulfilling it.
    </p>
@endsection
