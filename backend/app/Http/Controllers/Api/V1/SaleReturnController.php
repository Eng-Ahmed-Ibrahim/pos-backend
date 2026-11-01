<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseItems;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Sale;
use App\Models\saleItemBatches;
use App\Models\SaleReturn;
use App\Models\SaleReturnItems;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SaleReturnController extends Controller
{
    /**
     * GET /api/v1/purchase/return
     * بيانات الصفحة الأساسية: الموردين + الأقسام
     */
    public function index()
    {
        $suppliers = Supplier::select('id', 'name', 'phone')
            ->orderBy('name')
            ->get();

        $categories = Category::select('id', 'name', 'image')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status'     => true,
            'suppliers'  => $suppliers,
            'categories' => $categories,
        ]);
    }

    /**
     * GET /api/v1/purchase/return/products
     * منتجات مورد معيّن (فلترة بالقسم + بحث بالاسم/الباركود)
     */
    public function products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|exists:suppliers,id',
            'category_id' => 'nullable|exists:categories,id',
            'search'      => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $supplierId = $request->supplier_id;
        $categoryId = $request->category_id;
        $search     = trim((string) $request->search);

        // المتاح للإرجاع = مجموع remaining_stock من كل purchase_items
        // بتاعت فواتير المورد ده (نفس عمود remaining_stock المستخدم في مرتجع المبيعات)
        $query = PurchaseItems::query()
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'products.barcode as product_barcode',
                'products.category_id as category_id',
                DB::raw('SUM(purchase_items.remaining_stock) as available_qty'),
                DB::raw('MAX(purchase_items.price) as last_price')
            )
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->join('products', 'products.id', '=', 'purchase_items.product_id')
            ->where('purchases.supplier_id', $supplierId)
            ->where('purchase_items.remaining_stock', '>', 0)
            ->whereNull('purchases.deleted_at')
            ->groupBy('products.id', 'products.name', 'products.barcode', 'products.category_id')
            ->having('available_qty', '>', 0);

        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.barcode', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('products.name')->paginate(30);

        return response()->json([
            'status'   => true,
            'products' => $products,
        ]);
    }


    public function store(Request $request, $id)
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1'],

            'items.*.sale_item_id' => [
                'required',
                'integer',
                'exists:sale_items,id'
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'min:0'
            ],
        ]);

        try {

            $result = DB::transaction(function () use ($request, $id) {

                $sale = Sale::with('items')
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (!$sale) {
                    abort(422, 'فاتورة غير موجودة');
                }


                $saleReturn = SaleReturn::create([
                    'sale_id' => $sale->id,
                    'total_amount'   => 0,
                    'user_id'=>$request->user()->id
                ]);

                $returnTotal = 0;
                foreach ($request->items as $item) {

                    $quantity = (float) $item['quantity'];

                    // Ignore zero quantity
                    if ($quantity <= 0) {
                        continue;
                    }

                    $saleItem = $sale->items()
                        ->where('id', $item['sale_item_id'])
                        ->lockForUpdate()
                        ->first();

                    if (!$saleItem) {
                        abort(
                            422,
                            'الصنف غير موجود في هذه الفاتورة'
                        );
                    }


                    $originalQuantity = (float) $saleItem->quantity;

                    $returnedQuantity = (float) $saleItem->returned_quantity;

                    $availableQuantity = $originalQuantity - $returnedQuantity;

                    if ($quantity > $availableQuantity) {

                        abort(
                            422,
                            "الكميه المراد ارجاعها اكبر من الكميه المتاحه للصنف: {$saleItem->id}"
                        );
                    }

                    $batch = saleItemBatches::where(
                        'sale_items_id',
                        $saleItem->id
                    )->first();

                    if (!$batch) {
                        abort(
                            422,
                            "لا يوجد Batch للصنف {$saleItem->id}"
                        );
                    }

                    $purchaseItem = PurchaseItems::lockForUpdate()
                        ->find($batch->purchase_item_id);

                    if (!$purchaseItem) {
                        abort(
                            422,
                            "Purchase Item غير موجود"
                        );
                    }


                    $price = (float) $saleItem->price;

                    $itemTotal = $quantity * $price;

                    $returnTotal += $itemTotal;

                    SaleReturnItems::create([
                        'sale_return_id' => $saleReturn->id,
                        'sale_item_id'   => $saleItem->id,
                        'quantity'       => $quantity,
                        'price'          => $price,
                        'product_id'=>$saleItem->product_id,
                        'total'          => $itemTotal,
                    ]);

                    $saleItem->returned_quantity = $returnedQuantity + $quantity;

                    if (
                        $saleItem->returned_quantity >=
                        $originalQuantity
                    ) {

                        $saleItem->status = 'returned';
                    } else {

                        $saleItem->status = 'partially_returned';
                    }


                    $saleItem->save();

                    $purchaseItem->remaining_stock += $quantity;

                    $purchaseItem->save();
                }

                if ($returnTotal <= 0) {

                    abort(
                        422,
                        'يجب اختيار صنف واحد على الأقل للمرتجع'
                    );
                }

                $saleReturn->total_amount = $returnTotal;
                $sale->total -=$returnTotal;
                $saleReturn->save();
                $sale->save();


                return $saleReturn;
            });

            return response()->json([
                'message' => 'تم تسجيل المرتجع بنجاح',

                'return' => $result
                    ->load('items'),

            ], 201);
        } catch (\Throwable $e) {

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function refreshPurchaseStatus(int $purchaseId): void
    {
        $items = PurchaseItems::where('purchase_id', $purchaseId)->get();

        $totalQty      = $items->sum('quantity');
        $totalReturned = $items->sum('returned_quantity');

        if ($totalReturned <= 0) {
            $status = 'completed';
        } elseif ($totalReturned >= $totalQty) {
            $status = 'returned';
        } else {
            $status = 'partial';
        }

        DB::table('purchases')->where('id', $purchaseId)->update(['status' => $status]);
    }
}
