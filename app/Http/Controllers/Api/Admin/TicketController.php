<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\CriticalTicketNotification;
use App\Notifications\NewTicketNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class TicketController extends Controller
{
    /**
     * Paginated list of tickets with optional status and priority filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);

        $query = Ticket::with(['customer', 'user']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        $tickets = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $tickets->map(fn (Ticket $t) => [
                'ticket_id' => $t->ticket_id,
                'subject' => $t->subject,
                'status' => $t->status,
                'priority' => $t->priority,
                'customer_name' => $t->customer?->company_name,
                'assigned_user_name' => $t->user?->full_name,
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
     * Create a new ticket with status defaulting to Open.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:customers,company_id',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'nullable|string|in:Low,Normal,High,Critical',
            'ticket_type' => 'nullable|string|in:Incident,Service Request',
            'user_id' => 'nullable|integer|exists:users,id',
            'asset_id' => 'nullable|integer|exists:cmdb,device_id',
            'service_id' => 'nullable|integer|exists:services,service_id',
        ]);

        $validated['status'] = 'Open';

        $ticket = Ticket::create($validated);

        // Dispatch push notification to all admin users
        $ticket->load('customer');
        $adminUsers = User::where('is_admin', true)->get();
        Notification::send($adminUsers, new NewTicketNotification($ticket));

        return response()->json(['data' => $ticket], 201);
    }

    /**
     * Show a single ticket with replies, activities, and related details.
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
            'data' => array_merge($ticket->toArray(), [
                'customer_name' => $ticket->customer?->company_name,
                'assigned_user_name' => $ticket->user?->full_name,
                'replies' => $ticket->replies->map(fn ($reply) => [
                    'id' => $reply->id,
                    'body' => $reply->body,
                    'user_name' => $reply->user?->full_name,
                    'is_internal' => $reply->is_internal,
                    'created_at' => $reply->created_at,
                ]),
                'activities' => $ticket->activities->map(fn ($activity) => [
                    'id' => $activity->id,
                    'type' => $activity->type,
                    'description' => $activity->description,
                    'user_name' => $activity->user?->full_name,
                    'created_at' => $activity->created_at,
                ]),
            ]),
        ]);
    }

    /**
     * Update an existing ticket (status, priority, user_id).
     */
    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|required|string|in:Open,Pending,In Progress,Closed',
            'priority' => 'sometimes|required|string|in:Low,Normal,High,Critical',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $ticket->update($validated);

        // Dispatch critical ticket notification when priority is set to Critical
        if (isset($validated['priority']) && $validated['priority'] === 'Critical') {
            $ticket->load('customer');
            $adminUsers = User::where('is_admin', true)->get();
            Notification::send($adminUsers, new CriticalTicketNotification($ticket));
        }

        return response()->json(['data' => $ticket]);
    }

    /**
     * Add a reply to a ticket and set first_replied_at if first reply.
     */
    public function reply(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $reply = $ticket->replies()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        // Set first_replied_at if this is the first reply
        if (is_null($ticket->first_replied_at)) {
            $ticket->update(['first_replied_at' => now()]);
        }

        return response()->json(['data' => $reply], 201);
    }
}
