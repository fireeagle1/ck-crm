<?php

namespace App\Console\Commands;

use App\Models\ScheduledTaskLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TicketDailyDigest extends Command
{
    protected $signature = 'tickets:daily-digest';

    protected $description = 'Send a daily digest of open tickets to admin users at 8am';

    public function handle(): int
    {
        $log = ScheduledTaskLog::begin('tickets:daily-digest');

        $openTickets = Ticket::with(['customer', 'user'])
            ->whereIn('status', ['Open', 'Pending', 'In Progress'])
            ->orderByRaw("FIELD(priority, 'Critical', 'High', 'Normal', 'Low')")
            ->orderBy('created_at')
            ->get();

        // No open tickets — skip the email entirely
        if ($openTickets->isEmpty()) {
            $log->complete('No open tickets — digest skipped.');
            $this->info('No open tickets. Digest not sent.');
            return self::SUCCESS;
        }

        $admins = User::where('is_admin', true)->whereNotNull('email')->get();

        if ($admins->isEmpty()) {
            $log->complete('No admin users to notify.');
            $this->warn('No admin users found.');
            return self::SUCCESS;
        }

        $critical = $openTickets->where('priority', 'Critical')->count();
        $high = $openTickets->where('priority', 'High')->count();
        $normal = $openTickets->where('priority', 'Normal')->count();
        $low = $openTickets->where('priority', 'Low')->count();

        $overdue = $openTickets->filter(fn ($t) => $t->due_at && $t->due_at->isPast())->count();

        $sent = 0;
        foreach ($admins as $admin) {
            try {
                Mail::send('emails.ticket-daily-digest', [
                    'tickets' => $openTickets,
                    'recipientName' => $admin->first_name ?? 'Admin',
                    'totalOpen' => $openTickets->count(),
                    'critical' => $critical,
                    'high' => $high,
                    'normal' => $normal,
                    'low' => $low,
                    'overdue' => $overdue,
                ], function ($message) use ($admin, $openTickets) {
                    $message->to($admin->email)
                            ->subject("Daily Ticket Digest — {$openTickets->count()} open ticket" . ($openTickets->count() !== 1 ? 's' : ''));
                });
                $sent++;
            } catch (\Exception $e) {
                $this->error("Failed to send to {$admin->email}: {$e->getMessage()}");
            }
        }

        $log->complete("Digest sent to {$sent} admin(s). {$openTickets->count()} open tickets.", [
            'admins_notified' => $sent,
            'open_tickets' => $openTickets->count(),
        ]);

        $this->info("✓ Daily digest sent to {$sent} admin(s). {$openTickets->count()} open tickets.");

        return self::SUCCESS;
    }
}
