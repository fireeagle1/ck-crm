@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
        {{ $requestType }} Request
    </h2>

    <p style="margin:0 0 12px; font-size:14px; color:#374151; line-height:1.6;">
        Hi {{ $recipientName }},
    </p>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        A new service request has been submitted by <strong>{{ $requestUser->full_name }}</strong>
        ({{ $company?->company_name ?? 'Unknown company' }}).
    </p>

    <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:16px; margin:0 0 16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#374151;">
            <tr>
                <td style="padding:4px 0; font-weight:600; width:130px;">Type:</td>
                <td style="padding:4px 0;">{{ $requestType }}</td>
            </tr>
            @if ($service)
            <tr>
                <td style="padding:4px 0; font-weight:600;">Service:</td>
                <td style="padding:4px 0;">{{ $service->service_short }}{{ $service->domain_name ? " ({$service->domain_name})" : '' }}</td>
            </tr>
            @endif
            <tr>
                <td style="padding:4px 0; font-weight:600;">Customer:</td>
                <td style="padding:4px 0;">{{ $requestUser->full_name }} ({{ $requestUser->email }})</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Company:</td>
                <td style="padding:4px 0;">{{ $company?->company_name ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:16px; margin:0 0 16px;">
        <p style="margin:0 0 8px; font-size:14px; font-weight:600; color:#1f2937;">Details:</p>
        <p style="margin:0; font-size:14px; color:#374151; line-height:1.6; white-space:pre-wrap;">{{ $details }}</p>
    </div>

    @if ($company)
    <a href="{{ route('admin.customers.show', $company) }}" style="display:inline-block; padding:10px 20px; background-color:#2563eb; color:#ffffff; text-decoration:none; border-radius:4px; font-size:14px; font-weight:500;">
        View Customer
    </a>
    @endif
@endsection
