<?php

namespace App\Repositories;

use App\Contracts\PurchaseRepositoryInterface;
use App\Models\Purchase;

class PurchasesRepository implements PurchaseRepositoryInterface
{
    public function update(Purchase $purchase, $data)
    {
        $purchase->update($data);

        return $purchase;
    }
    public function create(array $data)
    {
        return Purchase::create($data);
    }
}
