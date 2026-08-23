<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\ScheduledTaskLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ActivateBookingsCommand extends Command
{
    protected $signature = 'app:activate-bookings';

    protected $description = 'Transition confirmed bookings to active status when their start date has arrived and the order is paid';

    public function handle(): int
    {
        $log = ScheduledTaskLog::begin('app:activate-bookings');

        // Find bookings that should now be active:
        // - Status is 'confirmed' (not yet started)
        // - Start date is today or in the past
        // - The associated order has been paid
        $bookings = Booking::where('status', 'confirmed')
            ->where('start_date', '<=', today())
            ->whereNotNull('company_id') // Exclude date blocks
            ->whereHas('orderItem.order', function ($query) {
                $query->whereIn('payment_status', ['paid', 'paid_offline']);
            })
            ->with(['product', 'customer'])
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No bookings to activate.');
            $log->complete('No bookings to activate.', ['count' => 0]);

            return Command::SUCCESS;
        }

        $activated = 0;
        $failed = 0;

        foreach ($bookings as $booking) {
            try {
                $booking->update(['status' => 'active']);
                $activated++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('ActivateBookings: Failed to activate booking', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $summary = "Activated {$activated} booking(s)." . ($failed > 0 ? " {$failed} failed." : '');
        $this->info($summary);
        Log::info('ActivateBookings: Completed', [
            'activated' => $activated,
            'failed' => $failed,
            'total' => $bookings->count(),
        ]);

        $log->complete($summary, [
            'activated' => $activated,
            'failed' => $failed,
        ]);

        return Command::SUCCESS;
    }
}
