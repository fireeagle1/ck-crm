<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketReply;
use App\Models\User;
use App\Notifications\CriticalTicketNotification;
use App\Notifications\NewTicketNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class TicketController extends Controller
{
    /**
     * Paginated list of tickets with optional status, priority, and customer filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);

        $query = Ticket::with(['customer', 'user', 'asset'])->withCount('replies');

        // Default to open tickets unless 'all' or specific status requested
        if ($status = $request->input('status')) {
            if ($status === 'open') {
                $query->whereIn('status', ['Open', 'Pending', 'In Progress']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        // Filter by customer
        if ($customerId = $request->input('customer_id')) {
            $query->where('company_id', $customerId);
        }

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('ticket_id', $search)
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('company_name', 'like', "%{$search}%");
                  });
            });
        }

        $tickets = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $tickets->map(fn (Ticket $t) => [
                'ticket_id' => $t->ticket_id,
                'subject' => $t->subject,
                'status' => $t->status,
                'priority' => $t->priority,
                'ticket_type' => $t->ticket_type,
                'customer_name' => $t->customer?->company_name,
                'company_id' => $t->company_id,
                'assigned_user_name' => $t->user?->full_name,
                'asset_name' => $t->asset?->device_name,
                'replies_count' => $t->replies_count,
                'created_at' => $t->created_at,
            ]),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    /**
     * Create a new ticket matching all web app options.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:customers,company_id',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|string|in:Low,Normal,High,Critical',
            'ticket_type' => 'required|string|in:Incident,Service Request',
            'request_category' => 'nullable|string|max:100',
            'user_id' => 'nullable|integer|exists:users,id',
            'asset_id' => 'nullable|integer|exists:cmdb,device_id',
            'service_id' => 'nullable|integer|exists:services,service_id',
            'notify_customer' => 'boolean',
        ]);

        $ticket = Ticket::create([
            'company_id' => $validated['company_id'],
            'user_id' => $validated['user_id'] ?? null,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'ticket_type' => $validated['ticket_type'],
            'priority' => $validated['priority'],
            'request_category' => $validated['request_category'] ?? null,
            'service_id' => $validated['service_id'] ?? null,
            'asset_id' => $validated['asset_id'] ?? null,
            'status' => 'Open',
        ]);

        // Send confirmation email to customer if requested
        if ($request->boolean('notify_customer')) {
            $this->notifyCustomerTicketCreated($ticket);
        }

        // Push notification to admins
        $ticket->load('customer');
        $adminUsers = User::where('is_admin', true)->get();
        Notification::send($adminUsers, new NewTicketNotification($ticket));

        return response()->json(['data' => $ticket->load(['customer', 'user', 'asset', 'service'])], 201);
    }

    /**
     * Show a single ticket with full details.
     */
    public function show(Ticket $ticket): JsonResponse
    {
        $ticket->load([
            'customer',
            'user',
            'asset',
            'service',
            'replies' => fn ($q) => $q->with('user')->orderBy('created_at', 'asc'),
            'activities' => fn ($q) => $q->with('user')->orderBy('created_at', 'asc'),
        ]);

        return response()->json([
            'data' => [
                'ticket_id' => $ticket->ticket_id,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'ticket_type' => $ticket->ticket_type,
                'request_category' => $ticket->request_category,
                'company_id' => $ticket->company_id,
                'customer_name' => $ticket->customer?->company_name,
                'user_id' => $ticket->user_id,
                'assigned_user_name' => $ticket->user?->full_name,
                'asset_id' => $ticket->asset_id,
                'asset_name' => $ticket->asset?->device_name,
                'service_id' => $ticket->service_id,
                'service_name' => $ticket->service?->service_short,
                'due_at' => $ticket->due_at,
                'first_replied_at' => $ticket->first_replied_at,
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->updated_at,
                'replies' => $ticket->replies->map(fn ($reply) => [
                    'id' => $reply->id,
                    'body' => $reply->body,
                    'user_name' => $reply->user?->full_name,
                    'is_internal' => (bool) $reply->is_internal,
                    'attachment_path' => $reply->attachment_path,
                    'created_at' => $reply->created_at,
                ]),
                'activities' => $ticket->activities->map(fn ($activity) => [
                    'id' => $activity->id,
                    'type' => $activity->type,
                    'old_value' => $activity->old_value,
                    'new_value' => $activity->new_value,
                    'user_name' => $activity->user?->full_name,
                    'created_at' => $activity->created_at,
                ]),
            ],
        ]);
    }

    /**
     * Update ticket — status, priority, type, asset, assignee, due date.
     */
    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|required|string|in:Open,Pending,In Progress,Closed',
            'priority' => 'sometimes|required|string|in:Low,Normal,High,Critical',
            'ticket_type' => 'sometimes|required|string|in:Incident,Service Request',
            'asset_id' => 'nullable|integer|exists:cmdb,device_id',
            'service_id' => 'nullable|integer|exists:services,service_id',
            'user_id' => 'nullable|integer|exists:users,id',
            'due_at' => 'nullable|date',
        ]);

        $changes = [];

        // Track changes for activity log
        if (isset($validated['status']) && $validated['status'] !== $ticket->status) {
            $changes[] = ['type' => 'status_changed', 'old_value' => $ticket->status, 'new_value' => $validated['status']];
        }
        if (isset($validated['priority']) && $validated['priority'] !== $ticket->priority) {
            $changes[] = ['type' => 'priority_changed', 'old_value' => $ticket->priority, 'new_value' => $validated['priority']];
        }
        if (isset($validated['ticket_type']) && $validated['ticket_type'] !== $ticket->ticket_type) {
            $changes[] = ['type' => 'type_changed', 'old_value' => $ticket->ticket_type, 'new_value' => $validated['ticket_type']];
        }
        if (array_key_exists('user_id', $validated) && (int) ($validated['user_id'] ?? 0) !== (int) ($ticket->user_id ?? 0)) {
            $oldUser = $ticket->user?->full_name ?? 'Unassigned';
            $newUser = isset($validated['user_id']) ? User::find($validated['user_id'])?->full_name ?? 'Unassigned' : 'Unassigned';
            $changes[] = ['type' => 'assigned_changed', 'old_value' => $oldUser, 'new_value' => $newUser];
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

        // Email customer on status change
        if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
            $this->notifyCustomerStatusChange($ticket, $oldStatus, $validated['status']);
        }

        // Push notification for critical priority
        if (isset($validated['priority']) && $validated['priority'] === 'Critical') {
            $ticket->load('customer');
            $adminUsers = User::where('is_admin', true)->get();
            Notification::send($adminUsers, new CriticalTicketNotification($ticket));
        }

        $ticket->load(['customer', 'user', 'asset', 'service']);

        return response()->json(['data' => $ticket]);
    }

    /**
     * Add a reply — supports internal notes and customer notification.
     */
    public function reply(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string',
            'is_internal' => 'boolean',
        ]);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->ticket_id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
            'is_internal' => $request->boolean('is_internal'),
        ]);

        // Track first reply time (only for non-internal)
        if (!$ticket->first_replied_at && !$request->boolean('is_internal')) {
            $ticket->update(['first_replied_at' => now()]);
        }

        // Re-open ticket if it was closed
        if ($ticket->status === 'Closed') {
            $ticket->update(['status' => 'Open']);
            TicketActivity::create([
                'ticket_id' => $ticket->ticket_id,
                'user_id' => $request->user()->id,
                'type' => 'status_changed',
                'old_value' => 'Closed',
                'new_value' => 'Open',
            ]);
        }

        // Email customer (only for non-internal replies)
        if (!$request->boolean('is_internal')) {
            $this->notifyCustomer($ticket, $reply);
        }

        return response()->json([
            'data' => [
                'id' => $reply->id,
                'body' => $reply->body,
                'user_name' => $request->user()->full_name,
                'is_internal' => (bool) $reply->is_internal,
                'created_at' => $reply->created_at,
            ],
        ], 201);
    }

    /**
     * Get assets, services, and users for a customer (used by ticket create form).
     */
    public function customerContext(Request $request): JsonResponse
    {
        $request->validate(['customer_id' => 'required|integer|exists:customers,company_id']);
        $customerId = $request->input('customer_id');

        $assets = Asset::where('customer_id', $customerId)
            ->where('asset_status', 'Active')
            ->orderBy('device_name')
            ->get(['device_id', 'device_name', 'device_type', 'location']);

        $services = Service::where('company_id', $customerId)
            ->where('status', 'Active')
            ->orderBy('service_short')
            ->get(['service_id', 'service_short', 'service_type']);

        $users = User::where('company_id', $customerId)
            ->where('is_admin', false)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        return response()->json([
            'assets' => $assets,
            'services' => $services->map(fn ($s) => ['service_id' => $s->service_id, 'service_short' => $s->service_short, 'service_type' => $s->service_type]),
            'users' => $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->first_name . ' ' . $u->last_name, 'email' => $u->email]),
        ]);
    }

    // --- Private email methods (same as web controller) ---

    private function notifyCustomer(Ticket $ticket, TicketReply $reply): void
    {
        $customer = $ticket->user;
        if (!$customer?->email) {
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
        } catch (\Exception) {}
    }

    private function notifyCustomerTicketCreated(Ticket $ticket): void
    {
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
            } catch (\Exception) {}
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
            } catch (\Exception) {}
        }
    }
}
