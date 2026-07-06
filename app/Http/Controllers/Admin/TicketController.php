<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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

    public function create(Request $request): View
    {
        $customers = Customer::orderBy('company_name')->get();
        $selectedCustomer = $request->get('customer_id');

        $assets = collect();
        $services = collect();

        if ($selectedCustomer) {
            $assets = Asset::where('customer_id', $selectedCustomer)
                ->where('asset_status', 'Active')
                ->orderBy('device_name')
                ->get();
            $services = Service::where('company_id', $selectedCustomer)
                ->where('status', 'Active')
                ->orderBy('service_short')
                ->get();
        }

        return view('admin.tickets.create', compact('customers', 'selectedCustomer', 'assets', 'services'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:customers,company_id',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'ticket_type' => 'required|in:Incident,Service Request',
            'priority' => 'required|in:Low,Normal,High,Critical',
            'request_category' => 'nullable|string|max:100',
            'service_id' => 'nullable|exists:services,service_id',
            'asset_id' => 'nullable|exists:cmdb,device_id',
            'notify_customer' => 'boolean',
        ]);

        // Find the primary user for this customer (to assign as ticket owner)
        $customerUser = User::where('company_id', $validated['company_id'])
            ->where('is_admin', false)
            ->first();

        $ticket = Ticket::create([
            'company_id' => $validated['company_id'],
            'user_id' => $customerUser?->id,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'ticket_type' => $validated['ticket_type'],
            'priority' => $validated['priority'],
            'request_category' => $validated['request_category'] ?? null,
            'service_id' => $validated['service_id'] ?? null,
            'asset_id' => $validated['asset_id'] ?? null,
            'status' => 'Open',
        ]);

        // Send confirmation email to customer if checkbox was ticked
        if ($request->boolean('notify_customer')) {
            $this->notifyCustomerTicketCreated($ticket);
        }

        return redirect()->route('admin.tickets.show', $ticket)
            ->with('success', 'Ticket INC' . $ticket->ticket_id . ' created successfully.');
    }

    public function show(Ticket $ticket): View
    {
        $ticket->load(['customer', 'user', 'asset', 'replies.user', 'activities.user']);

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
            'ticket_type' => 'in:Incident,Service Request',
            'asset_id' => 'nullable|exists:cmdb,device_id',
        ]);

        $changes = [];

        // Track status change
        if (isset($validated['status']) && $validated['status'] !== $ticket->status) {
            $changes[] = [
                'type' => 'status_changed',
                'old_value' => $ticket->status,
                'new_value' => $validated['status'],
            ];
        }

        // Track priority change
        if (isset($validated['priority']) && $validated['priority'] !== $ticket->priority) {
            $changes[] = [
                'type' => 'priority_changed',
                'old_value' => $ticket->priority,
                'new_value' => $validated['priority'],
            ];
        }

        // Track type change
        if (isset($validated['ticket_type']) && $validated['ticket_type'] !== $ticket->ticket_type) {
            $changes[] = [
                'type' => 'type_changed',
                'old_value' => $ticket->ticket_type,
                'new_value' => $validated['ticket_type'],
            ];
        }

        $oldStatus = $ticket->status;
        $ticket->update($validated);

        // Log activity
        foreach ($changes as $change) {
            TicketActivity::create([
                'ticket_id' => $ticket->ticket_id,
                'user_id' => $request->user()->id,
                'type' => $change['type'],
                'old_value' => $change['old_value'],
                'new_value' => $change['new_value'],
            ]);
        }

        // Email customer if status changed
        if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
            $this->notifyCustomerStatusChange($ticket, $oldStatus, $validated['status']);
        }

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
            $customer = User::where('company_id', $ticket->company_id)->first();
        }
        if (!$customer) return;

        try {
            Mail::send('emails.ticket-reply', [
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

    private function notifyCustomerTicketCreated(Ticket $ticket): void
    {
        // Find users for this company to notify
        $recipients = User::where('company_id', $ticket->company_id)
            ->where('is_admin', false)
            ->whereNotNull('email')
            ->get();

        if ($recipients->isEmpty()) return;

        foreach ($recipients as $recipient) {
            try {
                Mail::send('emails.ticket-opened', [
                    'ticket' => $ticket,
                    'recipientName' => $recipient->first_name ?? 'there',
                ], function ($message) use ($recipient, $ticket) {
                    $message->to($recipient->email)
                            ->subject("Ticket Opened: INC{$ticket->ticket_id} — {$ticket->subject}");
                });
            } catch (\Exception) {
                // Don't fail the request
            }
        }
    }

    private function notifyCustomerStatusChange(Ticket $ticket, string $oldStatus, string $newStatus): void
    {
        $recipients = User::where('company_id', $ticket->company_id)
            ->where('is_admin', false)
            ->whereNotNull('email')
            ->get();

        if ($recipients->isEmpty()) return;

        foreach ($recipients as $recipient) {
            try {
                Mail::send('emails.ticket-status-changed', [
                    'ticket' => $ticket,
                    'oldStatus' => $oldStatus,
                    'newStatus' => $newStatus,
                    'recipientName' => $recipient->first_name ?? 'there',
                ], function ($message) use ($recipient, $ticket, $newStatus) {
                    $message->to($recipient->email)
                            ->subject("INC{$ticket->ticket_id} Status Update: {$newStatus}");
                });
            } catch (\Exception) {
                // Don't fail the request
            }
        }
    }
}
