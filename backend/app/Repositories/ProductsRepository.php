<?php

namespace App\Repositories;

use App\Models\Product;

class ProductsRepository{
    public function get_products($productIds){
        return Product::whereIn('id', $productIds)
            ->lockForUpdate()
            ->withSum([
                'purchaseItems as current_stock' => function ($q) {
                    $q->where('remaining_stock', '>', 0);
                }
            ], 'remaining_stock')
            ->get()
            ->keyBy('id');
    }
}