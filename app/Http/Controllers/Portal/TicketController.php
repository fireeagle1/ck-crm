<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $tickets = Ticket::where('company_id', $request->user()->company_id)
            ->withCount('replies')
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('portal.tickets.index', compact('tickets'));
    }

    public function create(): View
    {
        return view('portal.tickets.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'in:Low,Normal,High,Critical',
        ]);

        Ticket::create([
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'priority' => $validated['priority'] ?? 'Normal',
            'status' => 'Open',
        ]);

        return redirect()->route('portal.tickets.index')
            ->with('success', 'Ticket submitted successfully.');
    }

    public function show(Request $request, Ticket $ticket): View
    {
        // Ensure the ticket belongs to the user's company
        if ($ticket->company_id !== $request->user()->company_id) {
            abort(403);
        }

        // Load replies but exclude internal notes
        $ticket->load(['user', 'replies' => function ($q) {
            $q->where('is_internal', false)->with('user')->orderBy('created_at');
        }]);

        return view('portal.tickets.show', compact('ticket'));
    }

    public function reply(Request $request, Ticket $ticket)
    {
        if ($ticket->company_id !== $request->user()->company_id) {
            abort(403);
        }

        $validated = $request->validate([
            'body' => 'required|string',
            'attachment' => 'nullable|file|max:10240',
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
            'is_internal' => false,
            'attachment_path' => $attachmentPath,
        ]);

        // Re-open if closed
        if ($ticket->status === 'Closed') {
            $ticket->update(['status' => 'Open']);
        }

        return back()->with('success', 'Reply sent.');
    }
}
