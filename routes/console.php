<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:premium-expire')->dailyAt('01:00');
Schedule::command('app:ops-alerts-scan')->everyFifteenMinutes();
Schedule::command('app:ops-ai-autonomous-approvals')->everyFifteenMinutes();
Schedule::command('app:billing-dunning-daily')->dailyAt('02:15');
Schedule::command('app:backup-database')->everyFourHours()->withoutOverlapping();
