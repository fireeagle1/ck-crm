<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $tickets = Ticket::where('company_id', $request->user()->company_id)
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

    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        return view('portal.tickets.show', compact('ticket'));
    }
}
