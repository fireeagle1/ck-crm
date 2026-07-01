@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
        Welcome to {{ \App\Models\Setting::get('site_name', 'CK Enterprises UK') }}
    </h2>

    <p style="margin:0 0 12px; font-size:14px; color:#374151; line-height:1.6;">
        Hi {{ $recipientName }},
    </p>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        An account has been created for you on our customer portal. You can use it to view your services, raise support tickets, and manage your account.
    </p>

    <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:16px; margin:0 0 16px;">
        <table style="width:100%; font-size:14px; color:#374151;">
            <tr>
                <td style="padding:4px 0; font-weight:600;">Email</td>
                <td style="padding:4px 0;">{{ $email }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Temporary Password</td>
                <td style="padding:4px 0; font-family:monospace;">{{ $password }}</td>
            </tr>
        </table>
    </div>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        Please log in and change your password at your earliest convenience.
    </p>

    <a href="{{ route('login') }}" style="display:inline-block; padding:12px 24px; background-color:#2563eb; color:#ffffff; text-decoration:none; border-radius:4px; font-size:14px; font-weight:600;">
        Log In to Your Portal
    </a>
@endsection
