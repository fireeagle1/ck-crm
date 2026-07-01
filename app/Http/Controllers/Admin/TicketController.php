<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        $query = Ticket::with(['customer', 'user'])->withCount('replies');

        if ($status !== 'all') {
            $query->whereIn('status', ['Open', 'Pending', 'In Progress']);
        }

        $tickets = $query->orderByDesc('created_at')->paginate(20);

        return view('admin.tickets.index', compact('tickets', 'status'));
    }

    public function show(Ticket $ticket): View
    {
        $ticket->load(['customer', 'user', 'replies.user']);

        return view('admin.tickets.show', compact('ticket'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'status' => 'required|in:Open,Pending,In Progress,Closed',
            'priority' => 'in:Low,Normal,High,Critical',
        ]);

        $ticket->update($validated);

        return back()->with('success', 'Ticket updated.');
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'body' => 'required|string',
            'is_internal' => 'boolean',
            'attachment' => 'nullable|file|max:10240', // 10MB max
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store(
                "tickets/{$ticket->ticket_id}",
                'public'
            );
        }

        TicketReply::create([
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

        return back()->with('success', 'Reply added.');
    }
}
