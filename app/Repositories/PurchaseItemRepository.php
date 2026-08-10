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
}