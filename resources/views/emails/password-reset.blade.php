@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
        Password Reset
    </h2>

    <p style="margin:0 0 12px; font-size:14px; color:#374151; line-height:1.6;">
        Hi,
    </p>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        You're receiving this email because we received a password reset request for your account.
    </p>

    <a href="{{ $url }}" style="display:inline-block; padding:12px 24px; background-color:#2563eb; color:#ffffff; text-decoration:none; border-radius:4px; font-size:14px; font-weight:600; margin-bottom:16px;">
        Reset Password
    </a>

    <p style="margin:16px 0 0; font-size:14px; color:#374151; line-height:1.6;">
        This link will expire in 60 minutes. If you did not request a password reset, no further action is required.
    </p>
@endsection
