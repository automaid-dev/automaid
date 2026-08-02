<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote')->hourly();

Schedule::command('automaid:assign-order-to-rider-and-merchant')->everyMinute();
Schedule::command('automaid:auto-insert-activity-next-day-delivery')->dailyAt('00:01');
Schedule::command('automaid:check-next-payment-subscription')->dailyAt('00:05');


