<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('adventures:sync-wiki')
    ->dailyAt('04:15')
    ->timezone('Europe/Berlin')
    ->withoutOverlapping(180)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/wiki-catalog-sync-schedule.log'));
