<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->get('status', 'open');

        $query = Ticket::with(['customer', 'user']);

        if ($status !== 'all') {
            $query->whereIn('status', ['Open', 'Pending', 'In Progress']);
        }

        $tickets = $query->orderByDesc('created_at')->paginate(20);

        return view('admin.tickets.index', compact('tickets', 'status'));
    }

    public function show(Ticket $ticket): View
    {
        $ticket->load(['customer', 'user']);

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
}
