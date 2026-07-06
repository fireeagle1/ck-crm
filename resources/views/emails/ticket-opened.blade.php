@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
        Ticket Opened — INC{{ $ticket->ticket_id }}
    </h2>

    <p style="margin:0 0 12px; font-size:14px; color:#374151; line-height:1.6;">
        Hi {{ $recipientName }},
    </p>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        We've opened a support ticket on your behalf. Our team is looking into it and will keep you updated.
    </p>

    <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:16px; margin:0 0 16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#374151;">
            <tr>
                <td style="padding:4px 0; font-weight:600; width:100px;">Ticket ID:</td>
                <td style="padding:4px 0;">INC{{ $ticket->ticket_id }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Subject:</td>
                <td style="padding:4px 0;">{{ $ticket->subject }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Type:</td>
                <td style="padding:4px 0;">{{ $ticket->ticket_type }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Priority:</td>
                <td style="padding:4px 0;">{{ $ticket->priority }}</td>
            </tr>
        </table>
    </div>

    @if ($ticket->description)
        <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:16px; margin:0 0 16px;">
            <p style="margin:0 0 4px; font-size:12px; color:#6b7280; font-weight:600;">Description</p>
            <p style="margin:0; font-size:14px; color:#1f2937; line-height:1.6; white-space:pre-wrap;">{{ Str::limit($ticket->description, 500) }}</p>
        </div>
    @endif

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        You can view and reply to this ticket from your portal at any time.
    </p>

    <a href="{{ route('portal.tickets.show', $ticket) }}" style="display:inline-block; padding:10px 20px; background-color:#2563eb; color:#ffffff; text-decoration:none; border-radius:4px; font-size:14px; font-weight:500;">
        View Ticket
    </a>
@endsection
