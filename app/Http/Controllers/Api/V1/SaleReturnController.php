<?php
// app/Http/Controllers/Api/V1/SaleReturnController.php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItems;
use App\Models\SaleReturn;
use App\Models\SaleReturnItems;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SaleReturnController extends Controller
{

    public function showSale( $id)
    {
        $sale = Sale::find($id);
        if (!$sale) {
            return response()->json([
                'status' => false,
                "message"=>"لا يوجد فاتوره بهذا الرقم"
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
                        ->lockForUpdate()
                        ->firstOrFail();

                    $remaining = $saleItem->quantity - $saleItem->returned_quantity;
                    $qty = (int) $row['quantity'];

                    if ($qty > $remaining) {
                        throw new \Exception(
                            "الكمية المطلوب إرجاعها أكبر من المتاح للصنف ({$saleItem->product_id}) - المتاح: {$remaining}"
                        );
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

                    // تحديث الكمية المرتجعة وحالة الصنف
                    $saleItem->returned_quantity += $qty;
                    $saleItem->status = $saleItem->returned_quantity >= $saleItem->quantity
                        ? 'returned'
                        : 'partially_returned';
                    $saleItem->save();

                    // إرجاع الكمية لمخزون المنتج
                    $product = $saleItem->product()->lockForUpdate()->first();
                    if ($product) {
                        $product->increment('stock', $qty);
                    }
                }

                $saleReturn->update(['total_amount' => $totalAmount]);

                // تحديث حالة الفاتورة ككل بناءً على حالة كل أصنافها
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
