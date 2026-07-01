@extends('emails.layout')

@section('content')
    <p style="margin:0 0 12px; font-size:14px; color:#374151; line-height:1.6;">
        Hi {{ $recipientName }},
    </p>

    <div style="font-size:14px; color:#374151; line-height:1.7;">
        {!! $emailBody !!}
    </div>

    <p style="margin:24px 0 0; font-size:14px; color:#374151; line-height:1.6;">
        Kind regards,<br>
        {{ \App\Models\Setting::get('site_name', 'CK Enterprises UK') }}
    </p>
@endsection
