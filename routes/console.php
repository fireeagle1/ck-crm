<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('stripe:sync')->dailyAt('02:00');
