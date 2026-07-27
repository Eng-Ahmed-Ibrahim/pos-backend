<?php

namespace App\Services;

use App\Models\InventorySnapshot;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryReportService
{
    public function takeMonthlySnapshot(Carbon $date): void
    {
        info("بدء الـ Monthly Snapshot لتاريخ {$date->toDateString()}...");

        $count = 0;

        $productIds = DB::table('purchase_items')
            ->distinct()
            ->pluck('product_id');

        foreach ($productIds->chunk(500) as $chunk) {
            $rows = [];

            foreach ($chunk as $productId) {
                $stockData = $this->calculateProductStock($productId);

                $rows[] = [
                    'snapshot_date' => $date,
                    'product_id' => $productId,
                    'remaining_stock' => $stockData['remaining_stock'],
                    'avg_cost_price' => $stockData['avg_cost_price'],
                    'total_value' => $stockData['remaining_stock'] * $stockData['avg_cost_price'],
                    'type' => 'monthly',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $count++;
            }

            InventorySnapshot::upsert(
                $rows,
                ['product_id', 'snapshot_date', 'type'],
                ['remaining_stock', 'avg_cost_price', 'total_value', 'updated_at']
            );
        }

        info("تم تسجيل {$count} منتج (اللي ليهم مشتريات فعلاً) في الـ Monthly Snapshot.");
    }

    public function takeDailySnapshotForMovedProducts(Carbon $date): void
    {
        info("بدء الـ Daily Snapshot لتاريخ {$date->toDateString()}...");

        $movedProductIds = DB::table('purchase_items')
            // ->whereDate('created_at', $date)
            ->pluck('product_id')
            ->merge(
                DB::table('sale_item_batches')
                    ->join('purchase_items', 'purchase_items.id', '=', 'sale_item_batches.purchase_item_id')
                    ->whereDate('sale_item_batches.created_at', $date)
                    ->pluck('purchase_items.product_id')
            )
            ->unique()
            ->values();

        if ($movedProductIds->isEmpty()) {
            info('مفيش منتجات اتحركت النهاردة.');
            return;
        }

        $rows = [];

        foreach ($movedProductIds as $productId) {
            $stockData = $this->calculateProductStock($productId);

            $rows[] = [
                'snapshot_date' => $date,
                'product_id' => $productId,
                'remaining_stock' => $stockData['remaining_stock'],
                'avg_cost_price' => $stockData['avg_cost_price'],
                'total_value' => $stockData['remaining_stock'] * $stockData['avg_cost_price'],
                'type' => 'daily',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        InventorySnapshot::upsert(
            $rows,
            ['product_id', 'snapshot_date', 'type'],
            ['remaining_stock', 'avg_cost_price', 'total_value', 'updated_at']
        );

        info("تم تسجيل {$movedProductIds->count()} منتج في الـ Daily Snapshot.");
    }


    public function getTotalStockValueFromSnapshot(Carbon $date): float
    {
        return (float) DB::table('inventory_snapshots as s1')
            ->joinSub(
                DB::table('inventory_snapshots')
                    ->where('snapshot_date', '<=', $date)
                    ->select('product_id', DB::raw('MAX(snapshot_date) as max_date'))
                    ->groupBy('product_id'),
                's2',
                fn($join) => $join->on('s1.product_id', '=', 's2.product_id')
                    ->on('s1.snapshot_date', '=', 's2.max_date')
            )
            ->sum('s1.total_value');
    }

    /**
     * تفصيل قيمة كل منتج لتاريخ معين، من الـ snapshot فقط (carry-forward)
     */
    public function getStockValueAsOfFromSnapshot(Carbon $date): Collection
    {
        info('getStockValueAsOfFromSnapshot111 ' . $date);
        $rows = DB::table('inventory_snapshots as s1')
            ->joinSub(
                DB::table('inventory_snapshots')
                    ->where('snapshot_date', '<=', $date)
                    ->select('product_id', DB::raw('MAX(snapshot_date) as max_date'))
                    ->groupBy('product_id'),
                's2',   
                fn($join) => $join->on('s1.product_id', '=', 's2.product_id')
                    ->on('s1.snapshot_date', '=', 's2.max_date')
            )
            ->select('s1.product_id', 's1.remaining_stock', 's1.avg_cost_price', 's1.total_value')
            ->get();

        return $rows->keyBy('product_id');
    }

    public function getStockValueAsOfFromLedger(Carbon $date): Collection
    {
        $batches = DB::table('purchase_items')
            ->where('created_at', '<=', $date)
            ->select('id', 'product_id', 'quantity', 'price')
            ->get();
        info("getStockValueAsOfFromLedger $date");

        if ($batches->isEmpty()) {
            return collect();
        }

        $batchIds = $batches->pluck('id');

        $sold = DB::table('sale_item_batches')
            ->whereIn('purchase_item_id', $batchIds)
            ->where('created_at', '<=', $date)
            ->select('purchase_item_id', DB::raw('SUM(qty) as qty'))
            ->groupBy('purchase_item_id')
            ->pluck('qty', 'purchase_item_id');

        $returned = DB::table('purchase_return_items')
            ->whereIn('purchase_item_id', $batchIds)
            ->where('created_at', '<=', $date)
            ->select('purchase_item_id', DB::raw('SUM(quantity) as qty'))
            ->groupBy('purchase_item_id')
            ->pluck('qty', 'purchase_item_id');

        $wasted = DB::table('waste_item_batches')
            ->whereIn('purchase_item_id', $batchIds)
            ->where('created_at', '<=', $date)
            ->select('purchase_item_id', DB::raw('SUM(quantity) as qty'))
            ->groupBy('purchase_item_id')
            ->pluck('qty', 'purchase_item_id');

        $perProduct = [];

        foreach ($batches as $batch) {
            $remaining = $batch->quantity
                - ($sold[$batch->id] ?? 0)
                - ($returned[$batch->id] ?? 0)
                - ($wasted[$batch->id] ?? 0);

            if ($remaining <= 0) {
                continue;
            }

            if (!isset($perProduct[$batch->product_id])) {
                $perProduct[$batch->product_id] = (object) [
                    'product_id' => $batch->product_id,
                    'remaining_stock' => 0,
                    'total_value' => 0,
                ];
            }

            $perProduct[$batch->product_id]->remaining_stock += $remaining;
            $perProduct[$batch->product_id]->total_value += $remaining * $batch->price;
        }

        return collect($perProduct);
    }

    public function getStockValueAsOf(Carbon $date): Collection
    {
        // get smallest value of date
        $firstSnapshotDate = DB::table('inventory_snapshots')->min('snapshot_date');

        // lt mean less than and this mean is from filter date is less than of smallest date in inventory ?
        if (!$firstSnapshotDate || $date->lt(Carbon::parse($firstSnapshotDate))) {
            return $this->getStockValueAsOfFromLedger($date);
        }

        return $this->getStockValueAsOfFromSnapshot($date);
    }

    /**
     * إجمالي قيمة البضاعة أول وآخر فترة، مع fallback تلقائي للـ ledger
     * مثال: getTotalStockValue('2026-07-01', '2026-07-31')
     */
    public function getTotalStockValue(string $startAt, string $endAt): array
    {
        $start = Carbon::parse($startAt)->startOfDay();
        $end = Carbon::parse($endAt)->endOfDay();

        // $start->copy()->subDay() get instance of start date without change anything of origin date 
        $startValue = $this->getStockValueAsOf($start->copy()->subDay())->sum('total_value');
        $endValue = $this->getStockValueAsOf($end)->sum('total_value');

        return [
            'start_at' => $start->toDateString(),
            'end_at' => $end->toDateString(),
            'start_value' => round($startValue, 2),
            'end_value' => round($endValue, 2),
            'value_change' => round($endValue - $startValue, 2),
        ];
    }

    /**
     * تفصيل قيمة كل منتج أول وآخر الفترة، مع fallback تلقائي للـ ledger
     */
    public function getProductsValueBetween(string $startAt, string $endAt): Collection
    {
        $start = Carbon::parse($startAt)->startOfDay();
        $end = Carbon::parse($endAt)->endOfDay();

        $opening = $this->getStockValueAsOf($start->copy()->subDay());
        $closing = $this->getStockValueAsOf($end);

        $productIds = $opening->keys()->merge($closing->keys())->unique()->values();

        $productNames = DB::table('products')
            ->whereIn('id', $productIds)
            ->pluck('name', 'id');

        return $productIds->map(function ($productId) use ($opening, $closing, $productNames) {
            $open = $opening->get($productId);
            $close = $closing->get($productId);

            return [
                'product_id' => $productId,
                'product_name' => $productNames[$productId] ?? null,
                'start_qty' => $open->remaining_stock ?? 0,
                'start_value' => round($open->total_value ?? 0, 2),
                'end_qty' => $close->remaining_stock ?? 0,
                'end_value' => round($close->total_value ?? 0, 2),
                'value_change' => round(($close->total_value ?? 0) - ($open->total_value ?? 0), 2),
            ];
        })->sortByDesc('end_value')->values();
    }

    /**
     * حركة المخزون (مشتريات/مرتجعات/هالك/مبيعات) خلال الفترة — من الـ ledger مباشرة دايماً
     */
    public function getInventoryReport(string $startAt, string $endAt): array
    {
        $start = Carbon::parse($startAt)->startOfDay();
        $end = Carbon::parse($endAt)->endOfDay();

        $purchasesValue = DB::table('purchase_items')
            ->whereBetween('created_at', [$start, $end])
            ->sum(DB::raw('quantity * price'));

        $purchaseReturnsValue = DB::table('purchase_return_items')
            ->whereBetween('created_at', [$start, $end])
            ->sum(DB::raw('quantity * price'));

        $wasteValue = DB::table('waste_item_batches')
            ->join('purchase_items', 'purchase_items.id', '=', 'waste_item_batches.purchase_item_id')
            ->whereBetween('waste_item_batches.created_at', [$start, $end])
            ->sum(DB::raw('waste_item_batches.quantity * purchase_items.price'));

        $soldCostValue = DB::table('sale_item_batches')
            ->join('purchase_items', 'purchase_items.id', '=', 'sale_item_batches.purchase_item_id')
            ->whereBetween('sale_item_batches.created_at', [$start, $end])
            ->sum(DB::raw('sale_item_batches.qty * purchase_items.price'));

        return [
            'purchases_value' => round($purchasesValue, 2),
            'purchase_returns_value' => round($purchaseReturnsValue, 2),
            'waste_value' => round($wasteValue, 2),
            'sold_cost_value' => round($soldCostValue, 2),
        ];
    }

    private function calculateProductStock(int $productId): array
    {
        $batches = DB::table('purchase_items')
            ->where('product_id', $productId)
            ->where('remaining_stock', '>', 0)
            ->select('remaining_stock', 'total', 'price')
            ->get();

        $totalStock = $batches->sum('remaining_stock');
        $totalValue = $batches->sum(fn($b) => $b->remaining_stock * $b->price);

        $avgCost = $totalStock > 0 ? $totalValue / $totalStock : 0;

        return [
            'remaining_stock' => $totalStock,
            'avg_cost_price' => round($avgCost, 3),
        ];
    }
}