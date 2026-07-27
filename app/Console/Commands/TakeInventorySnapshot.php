<?php

namespace App\Console\Commands;

use App\Models\InventorySnapshot;
use App\Services\InventoryReportService;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:take-inventory-snapshot')]
#[Description('Command description')]
class TakeInventorySnapshot extends Command
{

    public function handle(): void
    {
        info('Scheduler Works: ' . now());
        $today = Carbon::today();
        $service=app(InventoryReportService::class);
        $service->takeDailySnapshotForMovedProducts($today);
    }
}
