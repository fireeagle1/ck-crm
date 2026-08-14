<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\User;
use App\Notifications\InvoiceOverdueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendOverdueInvoiceNotifications extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'invoices:notify-overdue';

    /**
     * The console command description.
     */
    protected $description = 'Send push notifications for invoices that became overdue yesterday';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $yesterday = now()->subDay()->toDateString();

        $overdueInvoices = Invoice::where('invoice_status', 'Unpaid')
            ->whereDate('due_date', $yesterday)
            ->whereNotIn('invoice_status', Invoice::EXCLUDED_STATUSES)
            ->with('customer')
            ->get();

        if ($overdueInvoices->isEmpty()) {
            $this->info('No newly overdue invoices found.');
            return self::SUCCESS;
        }

        $adminUsers = User::where('is_admin', true)->get();

        if ($adminUsers->isEmpty()) {
            $this->warn('No admin users found to notify.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($overdueInvoices as $invoice) {
            Notification::send($adminUsers, new InvoiceOverdueNotification($invoice));
            $count++;
        }

        $this->info("Sent overdue notifications for {$count} invoice(s).");

        return self::SUCCESS;
    }
}
