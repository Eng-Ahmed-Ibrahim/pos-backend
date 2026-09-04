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
        $purchases = Purchase::withCount('items')
            ->withSum('items', 'total')
            ->with(['supplier'])
            ->orderBy("id", "desc")
            ->get();
        return response()->json([
            "status" => true,
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



    public function storeReturn(Request $request, $id)
    {
        $purchase = Purchase::with('items')->find($id);
        if (!$purchase) {
            return response()->json(['status' => false, 'message' => 'الفاتورة غير موجودة'], 404);
        }

        $validator = Validator::make($request->all(), [
            'reason' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_item_id' => ['required', 'integer', 'exists:purchase_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
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
            $purchaseReturn = DB::transaction(function () use ($data, $purchase) {
                $total = 0;
                $lines = [];

                foreach ($data['items'] as $row) {
                    // lockForUpdate عشان تمنع race condition لو في عملية بيع أو إرجاع تانية بتحصل في نفس اللحظة
                    $item = PurchaseItems::where('id', $row['purchase_item_id'])
                        ->where('purchase_id', $purchase->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$item) {
                        throw new \Exception('صنف غير تابع لهذه الفاتورة');
                    }

                    if ($row['quantity'] > $item->remaining_stock) {
                        throw new \Exception("الكمية المطلوب إرجاعها أكبر من المتاح للمنتج: {$item->product->name}");
                    }

                    $lineTotal = $row['quantity'] * $item->price;
                    $total += $lineTotal;

                    $item->decrement('remaining_stock', $row['quantity']);
                    $item->increment('returned_quantity', $row['quantity']);

                    $lines[] = [
                        'purchase_item_id' => $item->id,
                        'quantity' => $row['quantity'],
                        'price' => $item->price,
                        'total' => $lineTotal,
                    ];
                }

                $purchaseReturn = PurchaseReturn::create([
                    'purchase_id' => $purchase->id,
                    'total' => $total,
                    'reason' => $data['reason'] ?? null,
                ]);

                foreach ($lines as $line) {
                    $purchaseReturn->items()->create($line);
                }

                // تحديث حالة الفاتورة
                $purchase->refresh();
                $allReturned = $purchase->items->every(fn($i) => $i->remaining_stock == 0);
                $anyReturned = $purchase->items->some(fn($i) => $i->returned_quantity > 0)
                    ?? $purchase->items->contains(fn($i) => $i->returned_quantity > 0);

                $purchase->update([
                    'status' => $allReturned ? 'returned' : ($anyReturned ? 'partially_returned' : 'completed'),
                ]);

                Helpers::delete_products(); // نفس اللي بتعمله في store() عشان تحدّث أي cache

                return $purchaseReturn;
            });
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'تم تنفيذ إرجاع المشتريات بنجاح',
            'data' => ['sale' => $purchase->fresh()->load('items.product', 'supplier')],
        ], 201);
    }
}
