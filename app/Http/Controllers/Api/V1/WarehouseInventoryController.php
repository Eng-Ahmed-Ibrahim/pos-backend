<?php

namespace App\Http\Controllers\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\PurchaseItems;
use App\Http\Controllers\Controller;
use Mpdf\Mpdf;

class WarehouseInventoryController extends Controller
{
    public function index(Request $request)
    {
        $startOfMonth = Carbon::parse(now()->format('Y-m'))->startOfMonth();
        $endOfMonth   = Carbon::parse(now()->format('Y-m'))->endOfMonth();

        // Old batches (carried forward)
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

        // New purchases this month
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

        $inventory = $allProductIds->map(function ($productId) use ($carriedForward, $newPurchases) {

            $carried  = $carriedForward->get($productId, collect());
            $newItems = $newPurchases->get($productId, collect());

            $product = $carried->first()?->product
                ?? $newItems->first()?->product;

            // Carried forward quantity
            $carriedQty = $carried->sum('remaining_stock');

            // New purchases quantity
            $newQty = $newItems->sum('quantity')
                - $newItems->sum('returned_quantity');

            // Total available quantity
            $totalAvailable = $carriedQty + $newQty;

            // Current stock
            $currentStock = $carried->sum('remaining_stock')
                + $newItems->sum('remaining_stock');

            // Sold quantity
            $soldFromNew = $totalAvailable - $currentStock;

            // Current stock value based on purchase price
            $currentStockValue = $carried->sum(
                fn($item) => $item->remaining_stock * $item->price
            ) + $newItems->sum(
                fn($item) => $item->remaining_stock * $item->price
            );

            return [
                'product_id'          => $productId,
                'product_barcode'     => $product?->barcode,
                'product_name'        => $product?->name,

                'carried_forward'     => $carriedQty,
                'new_purchases'       => $newQty,
                'total_available'     => $totalAvailable,
                'sold'                => $soldFromNew,
                'current_stock'       => $currentStock,

                // Inventory valuation
                'current_stock_value' => round($currentStockValue, 2),

                'carried_batches' => $carried->map(fn($item) => [
                    'purchase_date' => $item->purchase->date,
                    'original_qty'  => $item->quantity,
                    'remaining'     => $item->remaining_stock,
                    'price'         => $item->price,
                    'expire_date'   => $item->expire_date,
                ]),
            ];
        })->values();

        // Total value of all current inventory
        $totalInventoryValue = $inventory->sum('current_stock_value');

        return response()->json([
            'status' => true,
            'data' => $inventory,
            'total_inventory_value' => round($totalInventoryValue, 2),
        ]);
    }


    public function exportPdf(Request $request)
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

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

        $inventory = $allProductIds->map(function ($productId) use (
            $carriedForward,
            $newPurchases
        ) {

            $carried = $carriedForward->get($productId, collect());
            $newItems = $newPurchases->get($productId, collect());

            $product = $carried->first()?->product
                ?? $newItems->first()?->product;

            $carriedQty = $carried->sum('remaining_stock');

            $newQty = $newItems->sum('quantity')
                - $newItems->sum('returned_quantity');

            $totalAvailable = $carriedQty + $newQty;

            $currentStock = $carried->sum('remaining_stock')
                + $newItems->sum('remaining_stock');

            $currentStockValue =
                $carried->sum(
                    fn($item) =>
                    $item->remaining_stock * $item->price
                )
                +
                $newItems->sum(
                    fn($item) =>
                    $item->remaining_stock * $item->price
                );

            return [
                'product_id' => $productId,
                'product_name' => $product?->name,
                'product_barcode' => $product?->barcode,

                'carried_forward' => $carriedQty,
                'new_purchases' => $newQty,
                'total_available' => $totalAvailable,
                'current_stock' => $currentStock,

                'current_stock_value' => round($currentStockValue, 2),
            ];
        })->values();

        if ($request->filled('search')) {
            $search = mb_strtolower($request->search);

            $inventory = $inventory->filter(function ($item) use ($search) {
                return str_contains(
                    mb_strtolower((string) $item['product_name']),
                    $search
                ) || str_contains(
                    mb_strtolower((string) $item['product_barcode']),
                    $search
                );
            })->values();
        }

        $totalInventoryValue = $inventory->sum('current_stock_value');

        $data = [
            'inventory' => $inventory,
            'totalInventoryValue' => round($totalInventoryValue, 2),
            'monthName' => Carbon::now()->translatedFormat('F Y'),
            'generatedAt' => Carbon::now()->format('Y-m-d H:i'),
        ];

        $html = view('pdf.warehouse_inventory', $data)->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'autoArabic' => true,
            'autoLangToFont' => true,
        ]);

        $mpdf->SetDirectionality('rtl');

        $mpdf->SetTitle('تقرير جرد المخزون');

        $mpdf->WriteHTML($html);

        $pdfOutput = $mpdf->Output('', 'S');

        return response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="warehouse-inventory.pdf"',
        ]);
    }
}
