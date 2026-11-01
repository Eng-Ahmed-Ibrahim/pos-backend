<?php 
namespace App\Contracts;

use App\Models\PurchaseItems;

interface PurchaseItemRepositoryInterface{
    public function create(array $data): PurchaseItems;
    public function update(PurchaseItems $item, array $data): PurchaseItems;
    public function delete(PurchaseItems $item): void;

} 