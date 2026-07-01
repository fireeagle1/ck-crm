@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
        Ticket Update — INC{{ $ticket->ticket_id }}
    </h2>

    <p style="margin:0 0 12px; font-size:14px; color:#374151; line-height:1.6;">
        Hi {{ $recipientName }},
    </p>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        A new reply has been added to your support ticket <strong>INC{{ $ticket->ticket_id }}: {{ $ticket->subject }}</strong>.
    </p>

    <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:16px; margin:0 0 16px;">
        <p style="margin:0 0 4px; font-size:12px; color:#6b7280;">
            {{ $reply->user?->full_name ?? 'Support Team' }} · {{ $reply->created_at->format('M j, Y \a\t H:i') }}
        </p>
        <p style="margin:0; font-size:14px; color:#1f2937; line-height:1.6; white-space:pre-wrap;">{{ Str::limit($reply->body, 500) }}</p>
    </div>

    <a href="{{ route('portal.tickets.show', $ticket) }}" style="display:inline-block; padding:10px 20px; background-color:#2563eb; color:#ffffff; text-decoration:none; border-radius:4px; font-size:14px; font-weight:500;">
        View Full Ticket
    </a>
@endsection
