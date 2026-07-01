<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'CK Enterprises UK' }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%;">
                    {{-- Header --}}
                    <tr>
                        <td style="background-color:#0f172a; padding:24px 32px; border-radius:8px 8px 0 0;">
                            @php $logoPath = \App\Models\Setting::get('logo_path'); @endphp
                            @if ($logoPath)
                                <img src="{{ asset('storage/' . $logoPath) }}" alt="{{ \App\Models\Setting::get('site_name', 'CK Enterprises UK') }}" style="height:32px; width:auto;">
                            @else
                                <span style="color:#ffffff; font-size:18px; font-weight:600; letter-spacing:-0.025em;">CK Enterprises UK</span>
                            @endif
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="background-color:#ffffff; padding:32px; border-left:1px solid #e5e7eb; border-right:1px solid #e5e7eb;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color:#f9fafb; padding:24px 32px; border:1px solid #e5e7eb; border-top:0; border-radius:0 0 8px 8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding-bottom:16px;">
                                        <a href="{{ route('portal.dashboard') }}" style="display:inline-block; padding:8px 16px; background-color:#2563eb; color:#ffffff; text-decoration:none; border-radius:4px; font-size:13px; font-weight:500; margin-right:8px;">
                                            Login to Portal
                                        </a>
                                        <a href="{{ route('portal.knowledgebase.index') }}" style="display:inline-block; padding:8px 16px; border:1px solid #d1d5db; color:#374151; text-decoration:none; border-radius:4px; font-size:13px; font-weight:500; margin-right:8px;">
                                            Knowledgebase
                                        </a>
                                        <a href="{{ route('portal.tickets.create') }}" style="display:inline-block; padding:8px 16px; border:1px solid #d1d5db; color:#374151; text-decoration:none; border-radius:4px; font-size:13px; font-weight:500;">
                                            Open Ticket
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-size:12px; color:#6b7280; line-height:1.5;">
                                        {{ \App\Models\Setting::get('site_name', 'CK Enterprises UK') }}<br>
                                        This email was sent from your customer management portal.<br>
                                        <a href="{{ route('portal.account.show') }}" style="color:#2563eb; text-decoration:none;">Manage your account</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
