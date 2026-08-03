<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('library:check-overdues')->dailyAt('00:05')->withoutOverlapping(10);
Schedule::command('library:process-reservations')->dailyAt('00:10')->withoutOverlapping(10);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
