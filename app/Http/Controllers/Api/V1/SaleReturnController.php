<?php
// app/Http/Controllers/Api/V1/SaleReturnController.php
namespace App\Http\Controllers\Api\V1;

use App\Models\Sale;
use App\Models\SaleItems;
use App\Models\SaleReturn;
use Illuminate\Http\Request;
use App\Models\SaleReturnItems;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class SaleReturnController extends Controller
{

    public function showSale($id)
    {
        $sale = Sale::find($id);
        if (!$sale) {
            return response()->json([
                'status' => false,
                "message" => "لا يوجد فاتوره بهذا الرقم"
            ]);
        }
        $sale->load(['items.product']);
        return response()->json([
            'status' => true,
            'data' => ['sale' => $sale],
        ]);
    }


    public function store(Request $request, Sale $sale)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|integer|exists:sale_items,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $saleReturn = DB::transaction(function () use ($request, $sale) {
                $saleReturn = SaleReturn::create([
                    'sale_id' => $sale->id,
                    'user_id' => $request->user()?->id,
                    'reason' => $request->input('reason'),
                    'total_amount' => 0,
                ]);

                $totalAmount = 0;

                foreach ($request->input('items') as $row) {
                    $saleItem = SaleItems::where('sale_id', $sale->id)
                        ->where('id', $row['sale_item_id'])
                        ->with('batches')
                        ->lockForUpdate()
                        ->firstOrFail();
                    $remaining = $saleItem->quantity - $saleItem->returned_quantity;
                    $qty = (int) $row['quantity'];

                    if ($qty > $remaining) {
                        throw new \Exception(
                            "الكمية المطلوب إرجاعها أكبر من المتاح للصنف ({$saleItem->product_id}) - المتاح: {$remaining}"
                        );
                    }
                    $remainingToReturn = $qty;

                    foreach ($saleItem->batches as $batch) {

                        if ($remainingToReturn <= 0) {
                            break;
                        }

                        $availableToReturn = $batch->qty - $batch->returned_qty;

                        if ($availableToReturn <= 0) {
                            continue;
                        }

                        $returnedQty = min($availableToReturn, $remainingToReturn);

                        $batch->purchaseItem()->increment('remaining_stock', $returnedQty);

                        $batch->increment('returned_qty', $returnedQty);
                        $batch->purchaseItem()->decrement('total_sold',$returnedQty);
                        $remainingToReturn -= $returnedQty;
                    }
                    $lineTotal = $qty * (float) $saleItem->price;
                    $totalAmount += $lineTotal;

                    SaleReturnItems::create([
                        'sale_return_id' => $saleReturn->id,
                        'sale_item_id' => $saleItem->id,
                        'product_id' => $saleItem->product_id,
                        'quantity' => $qty,
                        'price' => $saleItem->price,
                        'total' => $lineTotal,
                    ]);

                    $saleItem->returned_quantity += $qty;
                    $saleItem->total =($saleItem->quantity - $saleItem->returned_quantity) * $saleItem->price;
                    
                    $saleItem->status = $saleItem->returned_quantity >= $saleItem->quantity
                        ? 'returned'
                        : 'partially_returned';
                    $saleItem->save();
                }

                $saleReturn->update(['total_amount' => $totalAmount]);

                $sale->decrement('total', $totalAmount);
                $sale->refresh();
                $allItems = $sale->items()->get();
                $allReturned = $allItems->every(fn($i) => $i->returned_quantity >= $i->quantity);
                $anyReturned = $allItems->contains(fn($i) => $i->returned_quantity > 0);
                if ($allReturned) {
                    $sale->status = 'returned';
                } elseif ($anyReturned) {
                    $sale->status = 'partially_returned';
                }
                $sale->save();

                return $saleReturn;
            });
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'فشلت عملية الإرجاع',
            ], 422);
        }

        $sale->load(['items.product']);

        return response()->json([
            'status' => true,
            'message' => 'تم تنفيذ الإرجاع بنجاح',
            'data' => [
                'sale' => $sale,
                'sale_return' => $saleReturn->load('items'),
            ],
        ]);
    }
}
