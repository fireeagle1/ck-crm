@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
        Your Hosting Account is Ready
    </h2>

    <p style="margin:0 0 12px; font-size:14px; color:#374151; line-height:1.6;">
        Hi {{ $service->customer?->company_name ?? 'Customer' }},
    </p>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        Great news! Your hosting account has been provisioned and is ready to use. Below are your account details.
    </p>

    <div style="background-color:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:16px; margin:0 0 16px;">
        <table style="width:100%; font-size:14px; color:#374151;">
            <tr>
                <td style="padding:4px 0; font-weight:600;">Domain</td>
                <td style="padding:4px 0;">{{ $service->domain_name }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">cPanel Username</td>
                <td style="padding:4px 0;"><code style="background:#f3f4f6; padding:2px 6px; border-radius:3px;">{{ $service->cpanel_username }}</code></td>
            </tr>
        </table>
    </div>

    <h3 style="margin:16px 0 8px; font-size:15px; font-weight:600; color:#111827;">
        Nameserver Configuration
    </h3>

    <p style="margin:0 0 8px; font-size:14px; color:#374151; line-height:1.6;">
        To point your domain to your new hosting account, please update your domain's nameservers to:
    </p>

    <div style="background-color:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:16px; margin:0 0 16px;">
        @foreach($nameservers as $ns)
            <p style="margin:0 0 4px; font-size:14px; font-family:monospace; color:#1e40af;">
                {{ $ns }}
            </p>
        @endforeach
    </div>

    <p style="margin:0 0 12px; font-size:14px; color:#374151; line-height:1.6;">
        Nameserver changes can take up to 24&ndash;48 hours to propagate globally. Once propagation is complete, your website and email will be fully operational.
    </p>

    <p style="margin:0; font-size:13px; color:#6b7280; line-height:1.5;">
        If you need any assistance setting up your hosting or have questions about your account, please open a support ticket and our team will be happy to help.
    </p>
@endsection
