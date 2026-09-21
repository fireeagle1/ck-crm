<?php

use App\Models\ScheduledTaskLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// NOTE: This host disables proc_open, so Symfony Process (used internally by
// Schedule::command()) cannot spawn subprocesses. Every scheduled artisan
// command below runs via Artisan::call() inside Schedule::call(), which
// executes in-process instead of shelling out. Do not switch these back to
// Schedule::command() on this environment.

// Heartbeat — runs every 5 minutes to confirm cron is working
Schedule::call(function () {
    ScheduledTaskLog::begin('heartbeat')->complete('Cron is running.');
})->everyFiveMinutes();

// Stripe invoice & subscription sync — daily at 2am
Schedule::call(fn () => Artisan::call('stripe:sync'))->dailyAt('02:00');

// eNom domain sync — daily at 3am
Schedule::call(fn () => Artisan::call('enom:sync'))->dailyAt('03:00');

// Prune old task logs — daily at 4am, keep 30 days
Schedule::call(function () {
    $deleted = ScheduledTaskLog::prune(30);
    ScheduledTaskLog::begin('log:prune')->complete("Pruned {$deleted} old log entries.", ['deleted' => $deleted]);
})->dailyAt('04:00');

// Ticket daily digest — 8am, only sends if there are open tickets
Schedule::call(fn () => Artisan::call('tickets:daily-digest'))->dailyAt('08:00');

// Overdue invoice push notifications — daily at 7am, checks for invoices that became overdue yesterday
Schedule::call(fn () => Artisan::call('invoices:notify-overdue'))->dailyAt('07:00');

// Purge processed webhook events older than 7 days — daily at 2am
Schedule::call(fn () => Artisan::call('app:purge-webhook-events'))->dailyAt('02:00');

// Notify admin about rental bookings that have ended — daily at 8am
Schedule::call(fn () => Artisan::call('app:notify-rental-ended'))->dailyAt('08:00');

// Remind customers whose rental ends tomorrow — daily at 9am
Schedule::call(fn () => Artisan::call('app:notify-rental-ending-soon'))->dailyAt('09:00');

// Activate confirmed bookings whose start date has arrived — daily at 6am (before notifications)
Schedule::call(fn () => Artisan::call('app:activate-bookings'))->dailyAt('06:00');

// Clean up abandoned orders (unpaid after 2 hours) — every 30 minutes
Schedule::call(fn () => Artisan::call('app:cleanup-abandoned-orders'))->everyThirtyMinutes();

// Reset low-stock notification flags for restocked products — hourly
Schedule::call(fn () => Artisan::call('app:reset-low-stock-flags'))->hourly();

// Process queued jobs (emails, notifications) — runs every minute via cron
Schedule::call(fn () => Artisan::call('queue:work', [
    '--stop-when-empty' => true,
    '--tries' => 3,
]))->everyMinute()->name('queue-work')->withoutOverlapping();
