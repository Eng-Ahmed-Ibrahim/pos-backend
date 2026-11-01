<?php

namespace App\Console\Commands;

use App\Models\InventorySnapshot;
use App\Services\InventoryReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TakeInventorySnapshot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:take-inventory-snapshot';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Take daily inventory snapshot for moved products';

    public function handle(): void
    {
        info('Scheduler Works: ' . now());
        $today = Carbon::today();
        $service = app(InventoryReportService::class);
        $service->takeDailySnapshotForMovedProducts($today);
    }
}