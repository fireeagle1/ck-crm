<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->get('status', 'open');
        $search = $request->get('q', '');

        $query = Ticket::where('company_id', $request->user()->company_id)
            ->withCount('replies');

        // Filter by status
        if ($status === 'open') {
            $query->whereIn('status', ['Open', 'Pending', 'In Progress']);
        } elseif ($status === 'closed') {
            $query->where('status', 'Closed');
        }

        // Search by subject
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('ticket_id', $search);
            });
        }

        $tickets = $query->orderByDesc('created_at')->paginate(10);

        return view('portal.tickets.index', compact('tickets', 'status', 'search'));
    }

    public function create(Request $request): View
    {
        $companyId = $request->user()->company_id;

        $services = Service::where('company_id', $companyId)
            ->where('status', 'Active')
            ->orderBy('service_short')
            ->get();

        $assets = Asset::where('customer_id', $companyId)
            ->where('asset_status', 'Active')
            ->orderBy('device_name')
            ->get();

        $hasSupportPlan = Service::where('company_id', $companyId)
            ->where('service_type', 'Technical Support')
            ->where('status', 'Active')
            ->exists();

        return view('portal.tickets.create', compact('services', 'assets', 'hasSupportPlan'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'service_id' => 'nullable|exists:services,service_id',
            'asset_id' => 'nullable|exists:cmdb,device_id',
            'ticket_type' => 'in:Incident,Service Request',
            'request_category' => 'nullable|string|max:100',
            'attachment' => 'nullable|file|max:10240',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('tickets/attachments', 'public');
        }

        $ticket = Ticket::create([
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'service_id' => $validated['service_id'] ?? null,
            'asset_id' => $validated['asset_id'] ?? null,
            'ticket_type' => $validated['ticket_type'] ?? 'Incident',
            'request_category' => $validated['request_category'] ?? null,
            'attachment_path' => $attachmentPath,
            'priority' => 'Normal',
            'status' => 'Open',
        ]);

        // Notify all admins of new ticket
        $this->notifyAdminsNewTicket($ticket, $request->user());

        return redirect()->route('portal.tickets.index')
            ->with('success', 'Ticket submitted successfully. We\'ll get back to you soon.');
    }

    public function show(Request $request, Ticket $ticket): View
    {
        if ($ticket->company_id !== $request->user()->company_id) {
            abort(403);
        }

        $ticket->load(['user', 'service', 'asset', 'replies' => function ($q) {
            $q->where('is_internal', false)->with('user')->orderBy('created_at');
        }]);

        // Support agent (user ID 1)
        $agent = User::find(1);

        return view('portal.tickets.show', compact('ticket', 'agent'));
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

        $reply = TicketReply::create([
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

        // Email the admin/agent about the reply
        $this->notifyAgent($ticket, $reply);

        return back()->with('success', 'Reply sent.');
    }

    public function close(Request $request, Ticket $ticket)
    {
        if ($ticket->company_id !== $request->user()->company_id) {
            abort(403);
        }

        $ticket->update(['status' => 'Closed']);

        return back()->with('success', 'Ticket closed.');
    }

    private function notifyAgent(Ticket $ticket, TicketReply $reply): void
    {
        // Email all admin users
        $admins = User::where('is_admin', true)->whereNotNull('email')->get();

        foreach ($admins as $admin) {
            try {
                Mail::send('emails.ticket-reply', [
                    'ticket' => $ticket,
                    'reply' => $reply,
                    'recipientName' => $admin->first_name ?? 'Admin',
                ], function ($message) use ($admin, $ticket) {
                    $message->to($admin->email)
                            ->subject("Reply on INC{$ticket->ticket_id}: {$ticket->subject}");
                });
            } catch (\Exception) {
                // Don't fail the request if email fails
            }
        }
    }

    private function notifyAdminsNewTicket(Ticket $ticket, $submittedBy): void
    {
        $admins = User::where('is_admin', true)->whereNotNull('email')->get();

        foreach ($admins as $admin) {
            try {
                Mail::send('emails.ticket-new', [
                    'ticket' => $ticket,
                    'submittedBy' => $submittedBy,
                    'recipientName' => $admin->first_name ?? 'Admin',
                ], function ($message) use ($admin, $ticket) {
                    $message->to($admin->email)
                            ->subject("New Ticket INC{$ticket->ticket_id}: {$ticket->subject}");
                });
            } catch (\Exception) {
                // Don't fail
            }
        }
    }
}
