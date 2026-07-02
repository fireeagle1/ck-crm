<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->get('status', 'open');
        $search = $request->get('q', '');

        $query = Ticket::with(['customer', 'user', 'asset'])->withCount('replies');

        if ($status !== 'all') {
            $query->whereIn('status', ['Open', 'Pending', 'In Progress']);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('ticket_id', $search)
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('company_name', 'like', "%{$search}%");
                  });
            });
        }

        $tickets = $query->orderByDesc('created_at')->paginate(20);

        return view('admin.tickets.index', compact('tickets', 'status', 'search'));
    }

    public function show(Ticket $ticket): View
    {
        $ticket->load(['customer', 'user', 'asset', 'replies.user']);

        // Get assets for this customer to allow linking
        $assets = $ticket->company_id
            ? Asset::where('customer_id', $ticket->company_id)->get()
            : collect();

        return view('admin.tickets.show', compact('ticket', 'assets'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'status' => 'required|in:Open,Pending,In Progress,Closed',
            'priority' => 'in:Low,Normal,High,Critical',
            'asset_id' => 'nullable|exists:cmdb,device_id',
        ]);

        $ticket->update($validated);

        return back()->with('success', 'Ticket updated.');
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'body' => 'required|string',
            'is_internal' => 'boolean',
            'attachment' => 'nullable|file|max:10240',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store(
                "tickets/{$ticket->ticket_id}",
                'public'
            );
        }

        $reply = TicketReply::create([
            'ticket_id' => $ticket->ticket_id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
            'is_internal' => $request->boolean('is_internal'),
            'attachment_path' => $attachmentPath,
        ]);

        // Re-open ticket if it was closed and admin replies
        if ($ticket->status === 'Closed') {
            $ticket->update(['status' => 'Open']);
        }

        // Email the customer (only for non-internal replies)
        if (!$request->boolean('is_internal')) {
            $this->notifyCustomer($ticket, $reply);
        }

        return back()->with('success', 'Reply added.');
    }

    private function notifyCustomer(Ticket $ticket, TicketReply $reply): void
    {
        // Find the ticket owner
        $customer = $ticket->user;
        if (!$customer?->email) {
            // Fallback: first user on the company
            $customer = \App\Models\User::where('company_id', $ticket->company_id)->first();
        }
        if (!$customer) return;

        try {
            \Illuminate\Support\Facades\Mail::send('emails.ticket-reply', [
                'ticket' => $ticket,
                'reply' => $reply,
                'recipientName' => $customer->first_name ?? 'there',
            ], function ($message) use ($customer, $ticket) {
                $message->to($customer->email)
                        ->subject("Update on INC{$ticket->ticket_id}: {$ticket->subject}");
            });
        } catch (\Exception) {
            // Don't fail the request
        }
    }
}
