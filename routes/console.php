<?php

use App\Console\Commands\TakeInventorySnapshot;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(TakeInventorySnapshot::class)
    // ->dailyAt('23:55')
    ->everyMinute()
    ->withoutOverlapping(); // يمنع تشغيل نسخة تانية لو الأولى لسه شغالة
    // ->onOneServer(); // لو عندك أكتر من سيرفر، يضمن إنه يتنفذ مرة واحدة بس