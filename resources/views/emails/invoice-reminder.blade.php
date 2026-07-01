@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#111827;">
        Payment Reminder
    </h2>

    <p style="margin:0 0 12px; font-size:14px; color:#374151; line-height:1.6;">
        Hi {{ $recipientName }},
    </p>

    <p style="margin:0 0 16px; font-size:14px; color:#374151; line-height:1.6;">
        This is a friendly reminder that we have an outstanding invoice on your account.
    </p>

    <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:16px; margin:0 0 16px;">
        <table style="width:100%; font-size:14px; color:#374151;">
            <tr>
                <td style="padding:4px 0; font-weight:600;">Invoice</td>
                <td style="padding:4px 0;">#{{ $invoice->invoice_id }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Amount</td>
                <td style="padding:4px 0;">£{{ number_format($invoice->invoice_amount, 2) }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; font-weight:600;">Due Date</td>
                <td style="padding:4px 0; {{ $invoice->due_date?->isPast() ? 'color:#dc2626; font-weight:600;' : '' }}">
                    {{ $invoice->due_date?->format('M j, Y') ?? 'N/A' }}
                    @if ($invoice->due_date?->isPast())
                        (Overdue)
                    @endif
                </td>
            </tr>
        </table>
    </div>

    @if ($invoice->stripe_hosted_url)
        <a href="{{ $invoice->stripe_hosted_url }}" style="display:inline-block; padding:12px 24px; background-color:#2563eb; color:#ffffff; text-decoration:none; border-radius:4px; font-size:14px; font-weight:600; margin-bottom:16px;">
            Pay Now
        </a>
    @endif

    <p style="margin:16px 0 0; font-size:12px; color:#6b7280; line-height:1.5; border-top:1px solid #e5e7eb; padding-top:12px;">
        To verify the legitimacy of this invoice, please <a href="{{ route('portal.invoices.index') }}" style="color:#2563eb; text-decoration:none;">log in to your customer portal</a> to view this invoice directly.
    </p>
@endsection
