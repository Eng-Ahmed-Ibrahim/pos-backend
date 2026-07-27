<?php

namespace App\Http\Controllers\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\PurchaseItems;
use App\Http\Controllers\Controller;

class WarehouseInventoryController extends Controller
{
    public function index(Request $request)
    {
        $startOfMonth = Carbon::parse(now()->format('Y-m'))->startOfMonth();
        $endOfMonth   = Carbon::parse(now()->format('Y-m'))->endOfMonth();

        // old batches
        $carriedForward = PurchaseItems::with(['product', 'purchase'])
            ->whereHas(
                'purchase',
                fn($q) =>
                $q->where('date', '<', $startOfMonth)
                    ->whereNotIn('status', ['cancelled', 'rejected'])
            )
            ->where('remaining_stock', '>=', 0)
            ->get()
            ->groupBy('product_id');
        // new batches
        $newPurchases = PurchaseItems::with(['product', 'purchase'])
            ->whereHas(
                'purchase',
                fn($q) =>
                $q->whereBetween('date', [$startOfMonth, $endOfMonth])
                    ->whereNotIn('status', ['cancelled', 'rejected'])
            )
            ->get()
            ->groupBy('product_id');

            $allProductIds = $carriedForward->keys()
            ->merge($newPurchases->keys())
            ->unique();

        $inventory= $allProductIds->map(function ($productId) use ($carriedForward, $newPurchases) {

            $carried = $carriedForward->get($productId, collect());
            $newItems = $newPurchases->get($productId, collect());

            $product = $carried->first()?->product
                ?? $newItems->first()?->product;

            $carriedQty    = $carried->sum('remaining_stock') ; // carring forward quantity
            $newQty        = $newItems->sum('quantity') - $newItems->sum('returned_quantity'); // new purchases quantity
            $totalAvailable = $carriedQty + $newQty;
            $currentStock  = $carried->sum('remaining_stock') + $newItems->sum('remaining_stock');

            $soldFromNew   = $totalAvailable - $currentStock;
            return [
                'product_id'      => $productId,
                'product_barcode' =>$product?->barcode,
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
        return response()->json([
            'status' => true,
            'data' => $inventory,
        ]);
    }
}
