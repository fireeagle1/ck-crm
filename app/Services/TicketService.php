<?php

namespace App\Services;

use App\Mcp\Support\McpBotUserResolver;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketReply;
use App\Models\User;
use App\Notifications\CriticalTicketNotification;
use App\Notifications\NewTicketNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

/**
 * Shared ticket write logic, extracted verbatim from
 * {@see \App\Http\Controllers\Api\Admin\TicketController} so that both the
 * admin Sanctum API and the MCP ticket write tools create, update, and reply
 * to tickets through a single behaviour-preserving code path (Req 14.2).
 *
 * The only deviations from the original controller methods are structural, not
 * behavioural:
 *
 *  - The acting user is passed in as an explicit `$actor` parameter instead of
 *    being read from `request->user()`. The admin controller passes
 *    `request->user()` (a real admin); the MCP write tools pass the dedicated
 *    bot user ({@see McpBotUserResolver}).
 *  - Each persisted {@see TicketReply} / {@see TicketActivity} records a
 *    nullable `source` column ('admin' | 'mcp') inferred from the actor, so
 *    MCP-originated writes are distinguishable in audits. This is additive and
 *    does not change what the admin path observes.
 *  - Request-bound booleans (`notify_customer`, `is_internal`) are passed as
 *    explicit parameters rather than read from the HTTP request.
 *
 * All validation rules, status transitions, activity diffing, email sending,
 * and admin notifications are preserved exactly as in the original controller.
 */
final class TicketService
{
    /**
     * Create a ticket, matching the admin controller's `store()` behaviour.
     *
     * Persists the ticket with `status = 'Open'`; if `$notifyCustomer` is true,
     * emails all non-admin company users (`emails.ticket-opened`); and always
     * sends {@see NewTicketNotification} to every admin (Req 9.3, 9.4).
     *
     * @param  array<string, mixed>  $data  Raw input validated against the admin create rules.
     */
    public function create(array $data, User $actor, bool $notifyCustomer): Ticket
    {
        $validated = Validator::make($data, [
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
        ])->validate();

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
        if ($notifyCustomer) {
            $this->notifyCustomerTicketCreated($ticket);
        }

        // Push notification to admins
        $ticket->load('customer');
        $adminUsers = User::where('is_admin', true)->get();
        Notification::send($adminUsers, new NewTicketNotification($ticket));

        return $ticket->load(['customer', 'user', 'asset', 'service']);
    }

    /**
     * Update a ticket — status, priority, type, asset, assignee, due date —
     * matching the admin controller's `update()` behaviour.
     *
     * Validates the enum fields, diffs and writes {@see TicketActivity} rows
     * attributed to `$actor` (Req 10.2), emails the customer on status change
     * (Req 10.3), and sends {@see CriticalTicketNotification} to admins when the
     * priority becomes Critical (Req 10.4).
     *
     * @param  array<string, mixed>  $data  Raw input validated against the admin update rules.
     */
    public function update(Ticket $ticket, array $data, User $actor): Ticket
    {
        $validated = Validator::make($data, [
            'status' => 'sometimes|required|string|in:Open,Pending,In Progress,Closed',
            'priority' => 'sometimes|required|string|in:Low,Normal,High,Critical',
            'ticket_type' => 'sometimes|required|string|in:Incident,Service Request',
            'asset_id' => 'nullable|integer|exists:cmdb,device_id',
            'service_id' => 'nullable|integer|exists:services,service_id',
            'user_id' => 'nullable|integer|exists:users,id',
            'due_at' => 'nullable|date',
        ])->validate();

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

        $source = $this->sourceFor($actor);

        // Log activity
        foreach ($changes as $change) {
            TicketActivity::create([
                'ticket_id' => $ticket->ticket_id,
                'user_id' => $actor->id,
                'source' => $source,
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

        return $ticket;
    }

    /**
     * Add a reply — supports internal notes and customer notification —
     * matching the admin controller's `reply()` behaviour.
     *
     * Validates `body`/`is_internal`; attributes the {@see TicketReply} to
     * `$actor` (Req 11.5); sets `first_replied_at` once on the first non-internal
     * reply (Req 11.4); reopens Closed tickets to Open with a `status_changed`
     * activity (Req 11.3); and emails the customer for non-internal replies only
     * (Req 11.2).
     *
     * @param  array<string, mixed>  $data  Raw input validated against the admin reply rules.
     */
    public function reply(Ticket $ticket, array $data, User $actor, bool $isInternal): TicketReply
    {
        $validated = Validator::make($data, [
            'body' => 'required|string',
            'is_internal' => 'boolean',
        ])->validate();

        $source = $this->sourceFor($actor);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->ticket_id,
            'user_id' => $actor->id,
            'source' => $source,
            'body' => $validated['body'],
            'is_internal' => $isInternal,
        ]);

        // Track first reply time (only for non-internal)
        if (!$ticket->first_replied_at && !$isInternal) {
            $ticket->update(['first_replied_at' => now()]);
        }

        // Re-open ticket if it was closed
        if ($ticket->status === 'Closed') {
            $ticket->update(['status' => 'Open']);
            TicketActivity::create([
                'ticket_id' => $ticket->ticket_id,
                'user_id' => $actor->id,
                'source' => $source,
                'type' => 'status_changed',
                'old_value' => 'Closed',
                'new_value' => 'Open',
            ]);
        }

        // Email customer (only for non-internal replies)
        if (!$isInternal) {
            $this->notifyCustomer($ticket, $reply);
        }

        return $reply;
    }

    /**
     * Infer the write `source` from the acting user.
     *
     * The dedicated MCP bot user (identified by its stable email) yields 'mcp';
     * any other actor — i.e. a real admin via the controller path — yields
     * 'admin'. This keeps the public method signatures identical to the admin
     * controller's while still tagging MCP-originated writes for auditing.
     */
    private function sourceFor(User $actor): string
    {
        return $actor->email === McpBotUserResolver::BOT_EMAIL ? 'mcp' : 'admin';
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
