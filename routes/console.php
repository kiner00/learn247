<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:renew')->dailyAt('08:00');
Schedule::command('passwords:send-reminders')->dailyAt('09:00');

// Email sequences: process pending steps every minute
Schedule::command('email-sequences:process')->everyMinute();

// Cart abandonment: detect abandoned checkouts every 30 minutes
Schedule::command('carts:detect-abandoned --hours=1')->everyThirtyMinutes();

// Email analytics: aggregate daily stats at 2 AM
Schedule::command('email-stats:aggregate')->dailyAt('02:00');

// Ticket reminders: nudge users to close resolved tickets (every 2 days)
Schedule::command('tickets:remind-resolved')->dailyAt('10:00');

// Schedule::command('pulse:check')->everyMinute();
