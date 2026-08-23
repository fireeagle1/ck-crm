<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Order;
use App\Models\ScheduledTaskLog;
use App\Services\AssetAssignmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupAbandonedOrdersCommand extends Command
{
    protected $signature = 'app:cleanup-abandoned-orders
                            {--hours=2 : Hours after which an unpaid order is considered abandoned}';

    protected $description = 'Cancel orders stuck in "pending" payment status beyond the threshold, releasing reserved assets and freeing booking availability';

    public function handle(AssetAssignmentService $assetAssignmentService): int
    {
        $log = ScheduledTaskLog::begin('app:cleanup-abandoned-orders');

        $hours = (int) $this->option('hours');
        $cutoff = now()->subHours($hours);

        // Find orders that are still pending payment and older than the threshold.
        // Stripe Checkout sessions expire after ~24h by default; 2h is a safe window.
        $abandonedOrders = Order::where('payment_status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->whereNotNull('stripe_checkout_session_id')
            ->with('items.booking.assignedAssets')
            ->get();

        if ($abandonedOrders->isEmpty()) {
            $this->info('No abandoned orders found.');
            $log->complete('No abandoned orders found.', ['count' => 0]);

            return Command::SUCCESS;
        }

        $cleaned = 0;
        $assetsReleased = 0;
        $bookingsCancelled = 0;

        foreach ($abandonedOrders as $order) {
            try {
                DB::transaction(function () use ($order, $assetAssignmentService, &$assetsReleased, &$bookingsCancelled) {
                    // Cancel all bookings tied to this order and release their assets
                    foreach ($order->items as $item) {
                        if (!$item->booking) {
                            continue;
                        }

                        $booking = $item->booking;

                        // Release any reserved assets back to the pool
                        $activeAssets = $booking->assignedAssets()->whereNull('released_at')->count();
                        if ($activeAssets > 0) {
                            $assetAssignmentService->releaseAssets($booking);
                            $assetsReleased += $activeAssets;
                        }

                        // Cancel the booking so it no longer blocks availability
                        $booking->update(['status' => 'cancelled']);
                        $bookingsCancelled++;
                    }

                    // Mark the order as expired (distinct from admin-cancelled)
                    $order->update([
                        'payment_status' => 'expired',
                        'fulfilment_status' => 'cancelled',
                        'admin_notes' => trim(
                            ($order->admin_notes ? $order->admin_notes . "\n\n" : '')
                            . '[' . now()->format('Y-m-d H:i') . '] Auto-cancelled: payment not received within threshold.'
                        ),
                    ]);
                });

                $cleaned++;
            } catch (\Throwable $e) {
                Log::error('CleanupAbandonedOrders: Failed to clean order', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $summary = "Cleaned {$cleaned} abandoned order(s). Released {$assetsReleased} asset(s). Cancelled {$bookingsCancelled} booking(s).";
        $this->info($summary);
        Log::info('CleanupAbandonedOrders: Completed', [
            'cleaned' => $cleaned,
            'assets_released' => $assetsReleased,
            'bookings_cancelled' => $bookingsCancelled,
            'total_found' => $abandonedOrders->count(),
        ]);

        $log->complete($summary, [
            'cleaned' => $cleaned,
            'assets_released' => $assetsReleased,
            'bookings_cancelled' => $bookingsCancelled,
        ]);

        return Command::SUCCESS;
    }
}
