<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanks for getting in touch</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%;">
                    <tr>
                        <td align="center" style="background-color:#0f172a; padding:28px 32px; border-radius:8px 8px 0 0;">
                            @php $logoPath = \App\Models\Setting::get('logo_path'); @endphp
                            @if ($logoPath)
                                <img src="{{ asset($logoPath) }}" alt="{{ \App\Models\Setting::get('site_name', 'CK Enterprises UK') }}" style="height:44px; width:auto; display:block; margin:0 auto;">
                            @else
                                <span style="color:#ffffff; font-size:20px; font-weight:700; letter-spacing:-0.025em;">CK Enterprises UK</span>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#ffffff; padding:32px; border-left:1px solid #e5e7eb; border-right:1px solid #e5e7eb;">
                            <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
                                Thanks, {{ $lead['name'] }}
                            </h2>

                            <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
                                We've received your enquiry and a member of our team will be in touch soon to talk through how we can help.
                            </p>

                            <p style="margin:0 0 8px; font-size:14px; color:#374151; line-height:1.6;">
                                Here's a summary of what you sent us:
                            </p>

                            <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:16px; margin:0 0 16px;">
                                <table style="width:100%; font-size:14px; color:#374151;">
                                    <tr>
                                        <td style="padding:4px 0; font-weight:600; width:160px; vertical-align:top;">Organisation</td>
                                        <td style="padding:4px 0;">{{ $lead['organisation'] }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:4px 0; font-weight:600; vertical-align:top;">You'd like help with</td>
                                        <td style="padding:4px 0;">{{ implode(', ', $lead['services']) }}</td>
                                    </tr>
                                </table>
                                <p style="margin:12px 0 0; font-size:14px; color:#374151; line-height:1.6; white-space:pre-wrap;">{{ $lead['message'] }}</p>
                            </div>

                            <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
                                If anything's changed or you'd like to add more detail, just reply to this email.
                            </p>

                            <p style="margin:0; font-size:14px; color:#374151; line-height:1.6;">
                                Kind regards,<br>
                                The CK Enterprises team
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:16px 32px; background-color:#f9fafb; border:1px solid #e5e7eb; border-top:0; border-radius:0 0 8px 8px;">
                            <p style="margin:0; font-size:11px; color:#9ca3af; line-height:1.5;">
                                CK Enterprises Group Ltd is a registered company in England and Wales. Reg No. 11632973.<br>
                                You're receiving this because you submitted an enquiry at ckenterprises.co.uk.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
