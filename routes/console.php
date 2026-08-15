<?php

use App\Models\ScheduledTaskLog;
use Illuminate\Support\Facades\Schedule;

// Heartbeat — runs every 5 minutes to confirm cron is working
Schedule::call(function () {
    ScheduledTaskLog::begin('heartbeat')->complete('Cron is running.');
})->everyFiveMinutes();

// Stripe invoice & subscription sync — daily at 2am
Schedule::command('stripe:sync')->dailyAt('02:00');

// eNom domain sync — daily at 3am
Schedule::command('enom:sync')->dailyAt('03:00');

// Prune old task logs — daily at 4am, keep 30 days
Schedule::call(function () {
    $deleted = ScheduledTaskLog::prune(30);
    ScheduledTaskLog::begin('log:prune')->complete("Pruned {$deleted} old log entries.", ['deleted' => $deleted]);
})->dailyAt('04:00');

// Ticket daily digest — 8am, only sends if there are open tickets
Schedule::command('tickets:daily-digest')->dailyAt('08:00');

// Overdue invoice push notifications — daily at 7am, checks for invoices that became overdue yesterday
Schedule::command('invoices:notify-overdue')->dailyAt('07:00');

// Purge processed webhook events older than 7 days — daily at 2am
Schedule::command('app:purge-webhook-events')->dailyAt('02:00');

// Notify admin about rental bookings that have ended — daily at 8am
Schedule::command('app:notify-rental-ended')->dailyAt('08:00');

// Reset low-stock notification flags for restocked products — hourly
Schedule::command('app:reset-low-stock-flags')->hourly();

// Process queued jobs (emails, notifications) — runs every minute via cron
Schedule::command('queue:work --stop-when-empty --tries=3')->everyMinute()->withoutOverlapping();
