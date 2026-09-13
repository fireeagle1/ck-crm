<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New website enquiry</title>
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
                        <td style="background-color:#ffffff; padding:32px; border-left:1px solid #e5e7eb; border-right:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; border-radius:0 0 8px 8px;">
                            <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
                                New website enquiry
                            </h2>

                            <p style="margin:0 0 20px; font-size:14px; color:#374151; line-height:1.6;">
                                Someone completed the "How can we help?" form on the website. Details below.
                            </p>

                            <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:16px; margin:0 0 16px;">
                                <table style="width:100%; font-size:14px; color:#374151;">
                                    <tr>
                                        <td style="padding:6px 0; font-weight:600; width:180px; vertical-align:top;">Name</td>
                                        <td style="padding:6px 0;">{{ $lead['name'] }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:6px 0; font-weight:600; vertical-align:top;">Organisation</td>
                                        <td style="padding:6px 0;">{{ $lead['organisation'] }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:6px 0; font-weight:600; vertical-align:top;">Organisation type</td>
                                        <td style="padding:6px 0;">{{ $lead['organisation_type'] }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:6px 0; font-weight:600; vertical-align:top;">Email</td>
                                        <td style="padding:6px 0;"><a href="mailto:{{ $lead['email'] }}" style="color:#2563eb; text-decoration:none;">{{ $lead['email'] }}</a></td>
                                    </tr>
                                    @if(!empty($lead['phone']))
                                    <tr>
                                        <td style="padding:6px 0; font-weight:600; vertical-align:top;">Telephone</td>
                                        <td style="padding:6px 0;">{{ $lead['phone'] }}</td>
                                    </tr>
                                    @endif
                                    @if(!empty($lead['website']))
                                    <tr>
                                        <td style="padding:6px 0; font-weight:600; vertical-align:top;">Website</td>
                                        <td style="padding:6px 0;">{{ $lead['website'] }}</td>
                                    </tr>
                                    @endif
                                    @if(!empty($lead['preferred_contact']))
                                    <tr>
                                        <td style="padding:6px 0; font-weight:600; vertical-align:top;">Preferred contact</td>
                                        <td style="padding:6px 0;">{{ $lead['preferred_contact'] }}</td>
                                    </tr>
                                    @endif
                                    @if(!empty($lead['heard_about']))
                                    <tr>
                                        <td style="padding:6px 0; font-weight:600; vertical-align:top;">Heard about us via</td>
                                        <td style="padding:6px 0;">{{ $lead['heard_about'] }}</td>
                                    </tr>
                                    @endif
                                </table>
                            </div>

                            <h3 style="margin:16px 0 8px; font-size:15px; font-weight:600; color:#111827;">
                                What they need help with
                            </h3>
                            <ul style="margin:0 0 16px; padding-left:20px; font-size:14px; color:#374151; line-height:1.7;">
                                @foreach($lead['services'] as $service)
                                    <li>{{ $service }}</li>
                                @endforeach
                            </ul>

                            <h3 style="margin:16px 0 8px; font-size:15px; font-weight:600; color:#111827;">
                                What they're looking to achieve
                            </h3>
                            <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:16px; font-size:14px; color:#374151; line-height:1.6; white-space:pre-wrap;">{{ $lead['message'] }}</div>

                            <p style="margin:20px 0 0; font-size:12px; color:#9ca3af;">
                                Submitted {{ now()->format('M j, Y \a\t H:i') }}. Reply directly to this email to respond to {{ $lead['name'] }}.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
