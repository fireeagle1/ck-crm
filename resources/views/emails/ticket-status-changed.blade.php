@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
        Ticket Status Update — INC{{ $ticket->ticket_id }}
    </h2>

    <p style="margin:0 0 12px; font-size:14px; color:#374151; line-height:1.6;">
        Hi {{ $recipientName }},
    </p>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        The status of your support ticket <strong>INC{{ $ticket->ticket_id }}: {{ $ticket->subject }}</strong> has been updated.
    </p>

    <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:16px; margin:0 0 16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#374151;">
            <tr>
                <td style="padding:6px 0; font-weight:600; width:120px;">Previous Status:</td>
                <td style="padding:6px 0;">{{ $oldStatus }}</td>
            </tr>
            <tr>
                <td style="padding:6px 0; font-weight:600;">New Status:</td>
                <td style="padding:6px 0;">
                    <span style="display:inline-block; padding:2px 10px; border-radius:12px; font-size:13px; font-weight:600;
                        @if ($newStatus === 'Closed') background-color:#f3f4f6; color:#374151;
                        @elseif ($newStatus === 'In Progress') background-color:#fef3c7; color:#92400e;
                        @else background-color:#dcfce7; color:#166534;
                        @endif">
                        {{ $newStatus }}
                    </span>
                </td>
            </tr>
        </table>
    </div>

    @if ($newStatus === 'Closed')
        <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
            This ticket has been marked as resolved. If you still need assistance, you can reply via the portal and it will be re-opened automatically.
        </p>
    @elseif ($newStatus === 'In Progress')
        <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
            Our team is actively working on this. We'll update you as soon as there's progress.
        </p>
    @else
        <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
            You can view the full ticket and add a reply from your portal.
        </p>
    @endif

    <a href="{{ route('portal.tickets.show', $ticket) }}" style="display:inline-block; padding:10px 20px; background-color:#2563eb; color:#ffffff; text-decoration:none; border-radius:4px; font-size:14px; font-weight:500;">
        View Ticket
    </a>
@endsection
