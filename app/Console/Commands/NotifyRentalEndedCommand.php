<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\ScheduledTaskLog;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyRentalEndedCommand extends Command
{
    protected $signature = 'app:notify-rental-ended';

    protected $description = 'Send notifications for rental bookings whose end date has passed';

    public function handle(NotificationService $notificationService): int
    {
        $log = ScheduledTaskLog::begin('app:notify-rental-ended');

        $bookings = Booking::where('status', 'active')
            ->where('end_date', '<=', today())
            ->with(['product', 'customer'])
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No ended rentals found.');
            $log->complete('No ended rentals found.', ['count' => 0]);

            return Command::SUCCESS;
        }

        $notified = 0;

        foreach ($bookings as $booking) {
            try {
                $notificationService->notifyAdminRentalEnded($booking);
                $notified++;
            } catch (\Exception $e) {
                Log::error('NotifyRentalEnded: Failed to notify for booking', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Notified admin about {$notified} ended rental(s).");
        Log::info('NotifyRentalEnded: Completed', ['notified' => $notified, 'total' => $bookings->count()]);

        $log->complete("Notified admin about {$notified} ended rental(s).", [
            'notified' => $notified,
            'total' => $bookings->count(),
        ]);

        return Command::SUCCESS;
    }
}
