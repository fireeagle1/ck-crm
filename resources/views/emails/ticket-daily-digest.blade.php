@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
        Daily Ticket Digest
    </h2>

    <p style="margin:0 0 12px; font-size:14px; color:#374151; line-height:1.6;">
        Hi {{ $recipientName }},
    </p>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        You have <strong>{{ $totalOpen }} open ticket{{ $totalOpen !== 1 ? 's' : '' }}</strong> requiring attention.
    </p>

    {{-- Summary stats --}}
    <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:16px; margin:0 0 16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#374151;">
            @if ($critical > 0)
                <tr>
                    <td style="padding:4px 0; font-weight:600; color:#dc2626;">🔴 Critical:</td>
                    <td style="padding:4px 0; color:#dc2626; font-weight:600;">{{ $critical }}</td>
                </tr>
            @endif
            @if ($high > 0)
                <tr>
                    <td style="padding:4px 0; font-weight:600; color:#ea580c;">🟠 High:</td>
                    <td style="padding:4px 0; color:#ea580c; font-weight:600;">{{ $high }}</td>
                </tr>
            @endif
            <tr>
                <td style="padding:4px 0; font-weight:600;">🔵 Normal:</td>
                <td style="padding:4px 0;">{{ $normal }}</td>
            </tr>
            @if ($low > 0)
                <tr>
                    <td style="padding:4px 0; font-weight:600;">⚪ Low:</td>
                    <td style="padding:4px 0;">{{ $low }}</td>
                </tr>
            @endif
            @if ($overdue > 0)
                <tr>
                    <td style="padding:4px 0; font-weight:600; color:#dc2626;">⏰ Overdue:</td>
                    <td style="padding:4px 0; color:#dc2626; font-weight:600;">{{ $overdue }}</td>
                </tr>
            @endif
        </table>
    </div>

    {{-- Ticket list --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px; border:1px solid #e5e7eb; border-radius:6px; overflow:hidden; margin:0 0 16px;">
        <thead>
            <tr style="background-color:#f9fafb;">
                <th style="padding:8px 12px; text-align:left; font-weight:600; color:#374151; border-bottom:1px solid #e5e7eb;">ID</th>
                <th style="padding:8px 12px; text-align:left; font-weight:600; color:#374151; border-bottom:1px solid #e5e7eb;">Subject</th>
                <th style="padding:8px 12px; text-align:left; font-weight:600; color:#374151; border-bottom:1px solid #e5e7eb;">Customer</th>
                <th style="padding:8px 12px; text-align:left; font-weight:600; color:#374151; border-bottom:1px solid #e5e7eb;">Priority</th>
                <th style="padding:8px 12px; text-align:left; font-weight:600; color:#374151; border-bottom:1px solid #e5e7eb;">Status</th>
                <th style="padding:8px 12px; text-align:left; font-weight:600; color:#374151; border-bottom:1px solid #e5e7eb;">Age</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tickets as $ticket)
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:8px 12px;">
                        <a href="{{ route('admin.tickets.show', $ticket) }}" style="color:#2563eb; text-decoration:none; font-weight:500;">
                            INC{{ $ticket->ticket_id }}
                        </a>
                    </td>
                    <td style="padding:8px 12px; color:#1f2937;">{{ Str::limit($ticket->subject, 30) }}</td>
                    <td style="padding:8px 12px; color:#6b7280;">{{ $ticket->customer?->company_name ?? '—' }}</td>
                    <td style="padding:8px 12px;">
                        <span style="display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600;
                            @if ($ticket->priority === 'Critical') background-color:#fee2e2; color:#dc2626;
                            @elseif ($ticket->priority === 'High') background-color:#ffedd5; color:#ea580c;
                            @else background-color:#dbeafe; color:#2563eb;
                            @endif">
                            {{ $ticket->priority }}
                        </span>
                    </td>
                    <td style="padding:8px 12px; color:#6b7280;">{{ $ticket->status }}</td>
                    <td style="padding:8px 12px; color:#6b7280;">{{ $ticket->created_at->diffForHumans(null, true) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <a href="{{ route('admin.tickets.index') }}" style="display:inline-block; padding:10px 20px; background-color:#2563eb; color:#ffffff; text-decoration:none; border-radius:4px; font-size:14px; font-weight:500;">
        View All Tickets
    </a>
@endsection
