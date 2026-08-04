<?php

namespace App\Services;

use Exception;
use App\Models\Sale;
use App\Models\Product;
use App\Models\PurchaseItems;

class SalesService
{

    public function create_sale($validated, $user)
    {

        $sale  = $this->add_sale($validated, $user);
        [$products, $productIds] = $this->get_products($validated['items']);
        $allBatches = $this->get_batches($productIds);
        [$saleItemBatchesData, $saleItemsData] = $this->salesItems($validated, $products, $allBatches);
        $this->add_sale_batches($saleItemsData,$sale,$saleItemBatchesData);
        return $sale;
    }

    private function add_sale(array  $validated, object  $user): object
    {
        $total = collect($validated['items'])
            ->sum(fn($item) => $item['quantity'] * $item['price']);

        return  Sale::create([
            'customer_name' => $validated['customer_name'] ?? null,
            'amount_paid' => $validated['amount_paid'] ?? null,
            'total' => $total,
            'user_id' => $user->id,
            'payment_method'=>$validated['payment_method']
        ]);
    }
    private function get_products(array $items): array
    {
        $productIds = collect($items)
            ->pluck('product_id')
            ->unique();

        $products =  Product::whereIn('id', $productIds)
            ->lockForUpdate()
            ->withSum([
                'purchaseItems as current_stock' => function ($q) {
                    $q->where('remaining_stock', '>', 0);
                }
            ], 'remaining_stock')
            ->get()
            ->keyBy('id');
        return [$products, $productIds];
    }
    private function get_batches($productIds) 
    {
        $allBatches = PurchaseItems::whereIn('purchase_items.product_id', $productIds)
            ->where('purchase_items.remaining_stock', '>', 0)
            ->join(
                'purchases',
                'purchases.id',
                '=',
                'purchase_items.purchase_id'
            )
            ->whereNull('purchases.deleted_at')
            ->orderBy('purchases.date')
            ->select('purchase_items.*')
            ->lockForUpdate()
            ->get()
        ->groupBy('product_id');
        return $allBatches;
    }
    private function salesItems($validated, $products, $allBatches): array
    {
        $saleItemBatchesData = [];
        $saleItemsData = [];
        foreach ($validated['items'] as $item) {
            $product = $products[$item['product_id']] ?? null;

            if (!$product) {
                throw new Exception('المنتج غير موجود');
            }

            if ($product->current_stock < $item['quantity']) {

                throw new Exception(
                    "الكمية المطلوبة من {$product->name} غير متوفرة"
                );
            }

            $remainingToDeduct = $item['quantity'];
            $purchaseBatches =
                $allBatches[$item['product_id']] ?? collect();
            $batchAllocations = [];
            foreach ($purchaseBatches as $batch) {
                if ($remainingToDeduct <= 0) {
                    break;
                }
                $deductFromThis = min(
                    $batch->remaining_stock,
                    $remainingToDeduct
                );

                // update stock
                $batch->decrement(
                    'remaining_stock',
                    $deductFromThis
                );

                $batch->increment(
                    'total_sold',
                    $deductFromThis
                );

                // save FIFO relation
                $batchAllocations[] = [
                    'purchase_item_id' => $batch->id,
                    'qty' => $deductFromThis,
                    'cost_price' => $batch->price,
                ];

                $remainingToDeduct -= $deductFromThis;
            }

            if ($remainingToDeduct > 0) {
                throw new Exception(
                    "لا يوجد مخزون كافي للمنتج {$product->name}"
                );
            }

            $saleItemsData[] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'total' =>
                $item['quantity'] * $item['price'],
            ];
            $saleItemBatchesData[] = $batchAllocations;
        }
        return [$saleItemBatchesData, $saleItemsData];
    }
    private function add_sale_batches($saleItemsData,$sale,$saleItemBatchesData) 
    {
        foreach ($saleItemsData as $index => $saleItemData) {
            $saleItem = $sale->items()->create([
                ...$saleItemData,
            ]);

            foreach ($saleItemBatchesData[$index] as $allocation) {
                $saleItem->batches()->create([
                    'purchase_item_id' =>
                    $allocation['purchase_item_id'],

                    'qty' =>
                    $allocation['qty'],

                    'cost_price' =>
                    $allocation['cost_price'],
                ]);
            }
        }
    }
}
