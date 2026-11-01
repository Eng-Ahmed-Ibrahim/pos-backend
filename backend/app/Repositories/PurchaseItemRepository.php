<?php

namespace App\Repositories;

use App\Contracts\PurchaseItemRepositoryInterface as ContractsPurchaseItemRepositoryInterface;
use App\Models\PurchaseItems;

class PurchaseItemRepository implements ContractsPurchaseItemRepositoryInterface
{
    public function create(array $data): PurchaseItems
    {
        return PurchaseItems::create($data);
    }

    public function update(PurchaseItems $item, array $data): PurchaseItems
    {
        $item->update($data);
        return $item;
    }

    public function delete(PurchaseItems $item): void
    {
         $item->delete();
    }
    public function get_batches( $productIds){
        return PurchaseItems::whereIn('purchase_items.product_id', $productIds)
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
        ->groupBy('product_id') ;
    }
}