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
