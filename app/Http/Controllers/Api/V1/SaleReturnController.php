<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseItems;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
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

    /**
     * POST /api/v1/purchase/return
     *
     * Body:
     * {
     *   "supplier_id": 1,
     *   "reason": "منتج تالف",
     *   "items": [
     *      { "product_id": 5, "quantity": 3 }
     *   ]
     * }
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier_id'        => 'required|exists:suppliers,id',
            'reason'             => 'nullable|string|max:1000',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.001',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $supplierId = $request->supplier_id;

        try {
            $purchaseReturn = DB::transaction(function () use ($request, $supplierId) {
                $purchaseReturn = PurchaseReturn::create([
                    'supplier_id'  => $supplierId,
                    'user_id'      => $request->user()?->id,
                    'reason'       => $request->input('reason'),
                    'total_amount' => 0,
                ]);

                $totalAmount = 0;
                $affectedPurchaseIds = [];

                foreach ($request->input('items') as $row) {
                    $productId = (int) $row['product_id'];
                    $qty       = (float) $row['quantity'];

                    // كل الدفعات (purchase_items) المتاحة للمنتج ده من المورد ده
                    // LIFO: أحدث دفعة الأول - عدّل orderBy لو المفروض FIFO
                    $batches = PurchaseItems::query()
                        ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
                        ->where('purchases.supplier_id', $supplierId)
                        ->where('purchase_items.product_id', $productId)
                        ->where('purchase_items.remaining_stock', '>', 0)
                        ->orderByDesc('purchase_items.created_at')
                        ->select('purchase_items.*')
                        ->lockForUpdate()
                        ->get();

                    $available = $batches->sum('remaining_stock');

                    if ($qty > $available) {
                        throw new \Exception(
                            "الكمية المطلوب إرجاعها للمنتج رقم {$productId} أكبر من المتاح ({$available})"
                        );
                    }

                    $remainingToReturn = $qty;

                    foreach ($batches as $batch) {
                        if ($remainingToReturn <= 0) {
                            break;
                        }

                        $deductFromThisBatch = min($batch->remaining_stock, $remainingToReturn);

                        // خصم من الدفعة + تحديث حالة الفاتورة الأصلية
                        $batch->decrement('remaining_stock', $deductFromThisBatch);
                        $batch->returned_quantity = ($batch->returned_quantity ?? 0) + $deductFromThisBatch;
                        $batch->status = $batch->returned_quantity >= $batch->quantity
                            ? 'returned'
                            : 'partial';
                        $batch->save();

                        $lineTotal = $deductFromThisBatch * (float) $batch->price;
                        $totalAmount += $lineTotal;

                        PurchaseReturnItem::create([
                            'purchase_return_id' => $purchaseReturn->id,
                            'purchase_item_id'   => $batch->id,
                            'product_id'         => $productId,
                            'quantity'           => $deductFromThisBatch,
                            'price'              => $batch->price,
                            'total'              => $lineTotal,
                        ]);

                        $affectedPurchaseIds[$batch->purchase_id] = true;
                        $remainingToReturn -= $deductFromThisBatch;
                    }
                }

                $purchaseReturn->update(['total_amount' => $totalAmount]);

                // تحديث حالة كل فاتورة مشتريات اتأثرت
                foreach (array_keys($affectedPurchaseIds) as $purchaseId) {
                    $this->refreshPurchaseStatus($purchaseId);
                }

                return $purchaseReturn;
            });
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage() ?: 'فشلت عملية الإرجاع',
            ], 422);
        }

        return response()->json([
            'status'  => true,
            'message' => 'تم تنفيذ إرجاع المشتريات بنجاح',
            'data'    => [
                'purchase_return' => $purchaseReturn->load('items'),
            ],
        ]);
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
