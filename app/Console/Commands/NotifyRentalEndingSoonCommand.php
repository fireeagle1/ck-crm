<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyRentalEndingSoonCommand extends Command
{
    protected $signature = 'app:notify-rental-ending-soon';

    protected $description = 'Send reminder emails to customers whose rental ends tomorrow';

    public function handle(NotificationService $notificationService): int
    {
        $tomorrow = today()->addDay();

        $bookings = Booking::whereIn('status', ['confirmed', 'active'])
            ->whereDate('end_date', $tomorrow)
            ->with(['product', 'customer', 'orderItem.order'])
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No rentals ending tomorrow.');
            return Command::SUCCESS;
        }

        $notified = 0;

        foreach ($bookings as $booking) {
            try {
                $notificationService->notifyCustomerRentalEndingSoon($booking);
                $notified++;
            } catch (\Exception $e) {
                Log::error('NotifyRentalEndingSoon: Failed for booking', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Sent {$notified} rental ending reminder(s).");
        Log::info('NotifyRentalEndingSoon: Completed', ['notified' => $notified]);

        return Command::SUCCESS;
    }
}
