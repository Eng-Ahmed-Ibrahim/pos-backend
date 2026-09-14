<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Helpers\Helpers;
use App\Models\Category;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use App\Models\PurchaseItems;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePuchaseRequest;
use App\Http\Requests\UpdatePuchaseRequest;
use App\Models\PurchaseReturn;
use App\Services\PurchaseService;
use Illuminate\Support\Facades\Validator;

class PurchaseController extends Controller
{
    public function __construct(private PurchaseService $PurchaseService) {}
    public function index(Request $request)
    {
        
    $type = $request->type ?? "normal";
        $purchases = Purchase::withCount('items')
            ->where("type",$type)
            ->withSum('items', 'total')
            ->with(['supplier'])
            ->orderBy("id", "desc")
            ->get();
        return response()->json([
            "status" => true,
            "type"=>$type,
            "purchases" => $purchases
        ]);
    }
    public function show(Request $request, $id)
    {
        $purchase = Purchase::with(['supplier:id,name'])->findOrFail($id);

        $items = PurchaseItems::where('purchase_id', $purchase->id)
            ->with([
                'product',
                'product.unit:id,name',
            ])->paginate(15);
        return response()->json([
            'status' => true,
            'purchase' => $purchase,
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
                'per_page'     => $items->perPage(),
                'total'        => $items->total(),
            ],
            'items' => $items
        ]);
    }
    public function create(Request $request)
    {
        $categories = Helpers::cache_categories();
        $suppliers = Helpers::cache_suppliers();
        $sub_categories =  Helpers::cache_sub_categories();
        $products = Helpers::cache_all_products();
        $units = Helpers::cache_units();
        $data = [
            'count' => count($products),
            'size' => strlen(json_encode($products)),
            "categories" => $categories,
            "suppliers" => $suppliers,
            "sub_categories" => $sub_categories,
            "products" => $products,
            'units' => $units,
        ];
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }
    public function store(StorePuchaseRequest $request)
    {
        $validated = $request->validated();

        try {
            $purchase = DB::transaction(function () use ($validated, $request) {
                return $this->PurchaseService->createInvoice($validated, $request->file('image'));
            });

            return response()->json([
                'status' => true,
                'message' => 'تم حفظ الفاتورة بنجاح',
                'data' => $purchase->load('items.product', 'supplier'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
    public function update(UpdatePuchaseRequest $request, $id)
    {
        $purchase = Purchase::with('items')->findOrFail($id);
        $validated = $request->validated();
        try {
            $purchase = DB::transaction(function () use ($purchase, $validated, $request) {
                return $this->PurchaseService->updateInvoiceItems($purchase, $validated, $request->file('image'));
            });


            return response()->json([
                'status' => true,
                'message' => 'تم تحديث الفاتورة بنجاح',

                'data' => $purchase->load('items.product', 'supplier'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
    public function destroy($id)
    {
        $purchase = Purchase::find($id);
        $purchase->delete();
        return response()->json([
            "status" => true
        ]);
    }

    public function storeReturn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'reason' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric'],
        ], [
            'items.required' => 'يجب اختيار صنف واحد على الأقل',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $data = $validator->validated();

        try {
            $purchaseReturn = DB::transaction(function () use ($data) {
                $total = 0;
                $linesToCreate = [];

                // سنبحث عن بنود المشتريات المتاحة لهذا المورد والتي بها رصة متبقية (remaining_stock > 0)
                foreach ($data['items'] as $row) {
                    $qtyNeeded = $row['quantity'];

                    // جلب بنود الشراء غير المنتهية لهذا المنتج والمورد (يمكن ترتيبها حسب الأحدث أو الأقدم FIFO/LIFO)
                    $purchaseItems = PurchaseItems::where('product_id', $row['product_id'])
                        ->whereHas('purchase', function ($q) use ($data) {
                            $q->where('supplier_id', $data['supplier_id']);
                        })
                        ->where('remaining_stock', '>', 0)
                        ->orderBy('id', 'desc') // أو 'asc' حسب نظام شركتك
                        ->lockForUpdate()
                        ->get();

                    $availableTotalStock = $purchaseItems->sum('remaining_stock');

                    if ($qtyNeeded > $availableTotalStock) {
                        $productName = $purchaseItems->first()?->product?->name ?? 'المنتج';
                        throw new \Exception("الكمية المطلوب إرجاعها أكبر من المتاح للمنتج: {$productName}");
                    }

                    foreach ($purchaseItems as $item) {
                        if ($qtyNeeded <= 0) break;

                        $take = min($qtyNeeded, $item->remaining_stock);
                        $lineTotal = $take * $item->price;
                        $total += $lineTotal;

                        $item->decrement('remaining_stock', $take);
                        $item->increment('returned_quantity', $take);

                        $linesToCreate[] = [
                            'purchase_item_id' => $item->id,
                            'product_id' => $item->product_id, // أضف هذا السطر لتجنب خطأ عدم وجود قيمة افتراضية
                            'quantity' => $take,
                            'price' => $item->price,
                            'total' => $lineTotal,
                        ];

                        $qtyNeeded -= $take;

                        $purchase = $item->purchase;
                        $purchase->refresh();
                        $allReturned = $purchase->items->every(fn($i) => $i->remaining_stock == 0);
                        $anyReturned = $purchase->items->some(fn($i) => $i->returned_quantity > 0);

                        $purchase->update([
                            'status' => $allReturned ? 'returned' : ($anyReturned ? 'partial' : 'completed'),
                        ]);
                    }
                }

                $firstPurchaseItemId = $linesToCreate[0]['purchase_item_id'] ?? null;
                $firstPurchaseId = $firstPurchaseItemId ? PurchaseItems::find($firstPurchaseItemId)?->purchase_id : null;

                $purchaseReturn = PurchaseReturn::create([
                    // 'purchase_id' => $firstPurchaseId,
                    'supplier_id' => $data['supplier_id'],
                    'total' => $total,
                    'reason' => $data['reason'] ?? null,
                ]);

                foreach ($linesToCreate as $line) {
                    $purchaseReturn->items()->create($line);
                }

                Helpers::delete_products(); // تحديث الـ Cache

                return $purchaseReturn;
            });
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'تم تنفيذ إرجاع المشتريات بنجاح',
            'data' => $purchaseReturn,
        ], 201);
    }

    
}
