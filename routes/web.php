<?php

use App\Models\PurchaseItems;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
        $startOfMonth = Carbon::parse(now()->format('Y-m'))->startOfMonth();
        $endOfMonth   = Carbon::parse(now()->format('Y-m'))->endOfMonth();
        
        $carriedForward = PurchaseItems::with(['product', 'purchase'])
            ->whereHas('purchase', fn($q) =>
                $q->where('date', '<', $startOfMonth)
                  ->whereNotIn('status', ['cancelled', 'rejected'])
            )
            ->where('remaining_stock', '>', 0)
            ->get()
            ->groupBy('product_id');

        $newPurchases = PurchaseItems::with(['product', 'purchase'])
            ->whereHas('purchase', fn($q) =>
                $q->whereBetween('date', [$startOfMonth, $endOfMonth])
                  ->whereNotIn('status', ['cancelled', 'rejected'])
            )
            ->get()
            ->groupBy('product_id');

        $allProductIds = $carriedForward->keys()
            ->merge($newPurchases->keys())
            ->unique();

        return $allProductIds->map(function ($productId) use ($carriedForward, $newPurchases) {

            $carried = $carriedForward->get($productId, collect());
            $newItems = $newPurchases->get($productId, collect());

            $product = $carried->first()?->product
                    ?? $newItems->first()?->product;

            $carriedQty    = $carried->sum('remaining_stock');

            $newQty        = $newItems->sum('quantity');

            $newRemaining  = $product->stock;

            $totalAvailable = $carriedQty + $newQty;

            $currentStock  = $product->stock;

            $soldFromNew   = $totalAvailable - $currentStock ;

            return [
                'product_id'      => $productId,
                'product_name'    => $product?->name,
                'carried_forward' => $carriedQty,   
                'new_purchases'   => $newQty,        
                'total_available' => $totalAvailable,
                'sold'            => $soldFromNew,   
                'current_stock'   => $currentStock,  

                'carried_batches' => $carried->map(fn($item) => [
                    'purchase_date'   => $item->purchase->date,
                    'original_qty'    => $item->quantity,
                    'remaining'       => $item->remaining_stock,
                    'expire_date'     => $item->expire_date,
                ]),
            ];
        })->values();
    
});
