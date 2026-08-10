<?php 
namespace App\Contracts;

use App\Models\Purchase;

interface PurchaseRepositoryInterface{
    public function create(array $data);
    public function update(Purchase $purchase, array $data);

} 