<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseItems;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SaleController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => ['nullable', 'string', 'max:255'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ], [
            'items.required' => 'يجب إضافة صنف واحد على الأقل',
            'items.min' => 'يجب إضافة صنف واحد على الأقل',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $sale = DB::transaction(function () use ($validated) {
                $total = 0;
                foreach ($validated['items'] as $item) {
                    $total += $item['quantity'] * $item['price'];
                }

                $sale = Sale::create([
                    'customer_name' => $validated['customer_name'] ?? null,
                    'amount_paid' => $validated['amount_paid'] ?? null,
                    'total' => $total,
                ]);

                $saleItemsData = [];

                foreach ($validated['items'] as $item) {
                    // قفل صف المنتج عشان نمنع تعارض لو فيه عملية بيع تانية شغالة بالتوازى على نفس المنتج
                    $product = Product::where('id', $item['product_id'])->lockForUpdate()->first();

                    if (!$product) {
                        throw new \Exception('المنتج غير موجود');
                    }

                    if ($product->stock < $item['quantity']) {
                        throw new \Exception("الكمية المطلوبة من \"{$product->name}\" أكبر من المخزون المتاح ({$product->stock})");
                    }

                    // خصم الكمية من المخزون الكلى للمنتج
                    $product->decrement('stock', $item['quantity']);

                    // خصم الكمية من دفعات الشراء (purchase_items) بنظام FIFO:
                    // الأقدم دفعة يخصم منها أولًا، ولو خلصت ينتقل للدفعة اللى بعدها
                    $remainingToDeduct = $item['quantity'];


                    $purchaseBatches = PurchaseItems::where('purchase_items.product_id', $item['product_id'])
                        ->where('purchase_items.remaining_stock', '>', 0)
                        ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
                        ->whereNull('purchases.deleted_at')
                        ->orderBy('purchase_items.created_at')
                        ->select('purchase_items.*')
                        ->lockForUpdate()
                        ->get();
                        
                    foreach ($purchaseBatches as $batch) {
                        if ($remainingToDeduct <= 0) {
                            break;
                        }

                        $deductFromThis = min($batch->remaining_stock, $remainingToDeduct);
                        $batch->decrement('remaining_stock', $deductFromThis);
                        $remainingToDeduct -= $deductFromThis;
                    }

                    if ($remainingToDeduct > 0) {
                        // المخزون الكلى كان كافيًا لكن دفعات الشراء المسجلة مش كافية (عدم اتساق بيانات)
                        throw new \Exception("لا يوجد سجل دفعات شراء كافٍ لمنتج \"{$product->name}\" لإتمام البيع");
                    }

                    $itemTotal = $item['quantity'] * $item['price'];

                    $saleItemsData[] = [
                        'sale_id' => $sale->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'total' => $itemTotal,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                DB::table('sale_items')->insert($saleItemsData);

                return $sale;
            });
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'فشلت عملية البيع',
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'تم إتمام عملية البيع بنجاح',
            'data' => $sale->load('items.product'),
        ], 201);
    }
 
    public function show($id)
    {
        $sale = Sale::with('items.product')->findOrFail($id);

        return response()->json([
            'status' => true,
            'sale' => $sale,
        ]);
    }
}
