<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\ScheduledTaskLog;
use App\Services\OrderCancellationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupAbandonedOrdersCommand extends Command
{
    protected $signature = 'app:cleanup-abandoned-orders
                            {--hours=2 : Hours after which an unpaid order with a Stripe Checkout session is considered abandoned}';

    protected $description = 'Expire orders stuck in "pending" payment status beyond the threshold, restoring stock, cancelling bookings and releasing assets';

    public function handle(OrderCancellationService $cancellation): int
    {
        $log = ScheduledTaskLog::begin('app:cleanup-abandoned-orders');

        $cutoff = now()->subHours((int) $this->option('hours'));
        $hostingCutoff = now()->subHours((int) config('shop.abandoned_hosting_hours', 48));

        // Orders with a Checkout session: abandoned after --hours.
        // Orders without one (hosting-only, paid via subscriptions): abandoned after the longer hosting window.
        $candidateIds = Order::where('payment_status', 'pending')
            ->where(function ($q) use ($cutoff, $hostingCutoff) {
                $q->where(function ($q) use ($cutoff) {
                    $q->whereNotNull('stripe_checkout_session_id')
                        ->where('created_at', '<', $cutoff);
                })->orWhere(function ($q) use ($hostingCutoff) {
                    $q->whereNull('stripe_checkout_session_id')
                        ->where('created_at', '<', $hostingCutoff);
                });
            })
            ->pluck('id');

        if ($candidateIds->isEmpty()) {
            $this->info('No abandoned orders found.');
            $log->complete('No abandoned orders found.', ['count' => 0]);

            return Command::SUCCESS;
        }

        $cleaned = 0;

        foreach ($candidateIds as $orderId) {
            try {
                $expired = DB::transaction(function () use ($orderId, $cancellation) {
                    $order = Order::whereKey($orderId)->lockForUpdate()->first();

                    if ($order === null || $order->payment_status !== 'pending') {
                        return false;
                    }

                    // Restores stock, cancels bookings, releases assets, deletes never-provisioned
                    // Pending services and marks the order expired.
                    return $cancellation->expire($order);
                });

                if ($expired) {
                    $cleaned++;
                }
            } catch (\Throwable $e) {
                Log::error('CleanupAbandonedOrders: Failed to clean order', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $summary = "Expired {$cleaned} abandoned order(s).";
        $this->info($summary);
        Log::info('CleanupAbandonedOrders: Completed', [
            'cleaned' => $cleaned,
            'total_found' => $candidateIds->count(),
        ]);

        $log->complete($summary, ['cleaned' => $cleaned]);

        return Command::SUCCESS;
    }
}
