<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    public function store(Request $request, TicketService $tickets): JsonResponse
    {
        $ticket = $tickets->create(
            $request->all(),
            $request->user(),
            $request->boolean('notify_customer'),
        );

        return response()->json(['data' => $ticket], 201);
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
    public function update(Request $request, Ticket $ticket, TicketService $tickets): JsonResponse
    {
        $ticket = $tickets->update($ticket, $request->all(), $request->user());

        return response()->json(['data' => $ticket]);
    }

    /**
     * Add a reply — supports internal notes and customer notification.
     */
    public function reply(Request $request, Ticket $ticket, TicketService $tickets): JsonResponse
    {
        $reply = $tickets->reply(
            $ticket,
            $request->all(),
            $request->user(),
            $request->boolean('is_internal'),
        );

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
}
