<?php
namespace App\Services;

use App\Models\PurchaseItems;

class StockDeductionService
{

    public function deduct(int $productId, float $quantity): array
    {
        $remaining = $quantity;
        $deductions = [];

        $batches = PurchaseItems::where('product_id', $productId)
            ->where('remaining_stock', '>', 0)
            ->orderBy('created_at') // FIFO
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;
            $deductFromBatch = min($batch->remaining_stock, $remaining);
            $batch->decrement('remaining_stock', $deductFromBatch);
            $deductions[] = [
                'purchase_item_id' => $batch->id,
                'quantity' => $deductFromBatch,
            ];
            $remaining -= $deductFromBatch;
        }

        if ($remaining > 0) {
            throw new \Exception('الكمية المطلوبة أكبر من المتاح في المخزون');
        }

        return $deductions;
    }
}