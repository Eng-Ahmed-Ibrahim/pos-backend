<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItems;
use App\Models\SubCategory;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Helpers\Helpers;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $purchases = Purchase::with(['supplier'])->withCount('items')->orderBy("id", "desc")->get();

        return response()->json([
            "status" => true,
            "purchases" => $purchases
        ]);
    }
    public function show(Request $request, $id)
    {
        $purchase = Purchase::with([
            'supplier',
            'items.product' => function ($q) {
                $q->withTrashed();
            }
        ])->findOrFail($id);
        return response()->json([
            "status" => true,
            "purchase" => $purchase
        ]);
    }
    public function create(Request $request)
    {
        $categories = Helpers::cache_categories();
        $suppliers = Helpers::cache_suppliers();
        $sub_categories =  Helpers::cache_sub_categories();
        $products = Helpers::cache_products();
        $data = [
            "categories" => $categories,
            "suppliers" => $suppliers,
            "sub_categories" => $sub_categories,
            "products" => $products
        ];
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'date' => ['required', 'date'],
            'image' => 'required|image',

            // 'invoice_number' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.expire_date' => ['required', 'date'],
        ], [
            'supplier_id.required' => 'المورد مطلوب',
            'date.required' => 'تاريخ الفاتورة مطلوب',
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

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/categories'), $imageName);

            $validated['image'] = 'uploads/categories/' . $imageName;
        }
        $purchase = DB::transaction(function () use ($validated) {
            $total = 0;
            foreach ($validated['items'] as $item) {
                $total += $item['quantity'] * $item['price'];
            }

            $purchase = Purchase::create([
                'supplier_id' => $validated['supplier_id'],
                // 'invoice_number' => $validated['invoice_number'] ?? null,
                'date' => $validated['date'],
                'total' => $total,
                'image' => $validated['image']
            ]);

            foreach ($validated['items'] as $item) {
                $total = $item['quantity'] * $item['price'];

                PurchaseItems::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'remaining_stock' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $total,
                    "expire_date" => $item['expire_date']
                ]);

                // تحديث المخزون: إضافة الكمية المُشتراة لمخزون المنتج
                $product = Product::find($item['product_id']);
                $product->increment('stock', $item['quantity']);
            }
            Helpers::delete_products();
            return $purchase;
        });

        return response()->json([
            'status' => true,
            'message' => 'تم حفظ الفاتورة بنجاح',
            'data' => $purchase->load('items.product', 'supplier'),
        ], 201);
    }
    public function update(Request $request, $id)
    {
        $purchase = Purchase::with('items')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'date' => ['required', 'date'],
            'image' => ['nullable', 'image', 'mimes:jpg,png,jpeg'],
            'notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.expire_date' => ['required', 'date'],
        ], [
            'supplier_id.required' => 'المورد مطلوب',
            'date.required' => 'تاريخ الفاتورة مطلوب',
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

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/categories'), $imageName);
            $validated['image'] = 'uploads/categories/' . $imageName;

            if ($purchase->image && file_exists(public_path($purchase->image))) {
                @unlink(public_path($purchase->image));
            }
        }   
        return response()->json([$purchase->items->keyBy('product_id')]);

        $purchase = DB::transaction(function () use ($purchase, $validated) {

            $oldItemsMap = $purchase->items->keyBy('product_id');

            $newItemsMap = collect($validated['items'])->keyBy('product_id');

            foreach ($oldItemsMap as $productId => $oldItem) {

                if ($newItemsMap->has($productId)) {
                    $newItem = $newItemsMap[$productId];
                    $qtyDiff = $newItem['quantity'] - $oldItem->quantity;

                    $newRemaining = $oldItem->remaining_stock + $qtyDiff;

                    if ($newRemaining < 0) {
                        throw new \Exception("لا يمكن تقليل الكمية، تم بيع جزء منها بالفعل");
                    }

                    $product = Product::find($productId);
                    if ($qtyDiff > 0) {
                        $product->increment('stock', $qtyDiff);
                    } elseif ($qtyDiff < 0) {
                        $product->decrement('stock', abs($qtyDiff));
                    }

                    $oldItem->update([
                        'quantity'        => $newItem['quantity'],
                        'remaining_stock' => $newRemaining,
                        'price'           => $newItem['price'],
                        'total'           => $newItem['quantity'] * $newItem['price'],
                        'expire_date'     => $newItem['expire_date'],
                    ]);
                } else {
                    if ($oldItem->remaining_stock < $oldItem->quantity) {
                        throw new \Exception("لا يمكن حذف \"{$oldItem->product->name}\" لأنه تم بيع جزء منه");
                    }
                    Product::find($productId)?->decrement('stock', $oldItem->quantity);
                    $oldItem->delete();
                }
            }

            foreach ($newItemsMap as $productId => $newItem) {
                if (!$oldItemsMap->has($productId)) {
                    PurchaseItems::create([
                        'purchase_id'     => $purchase->id,
                        'product_id'      => $productId,
                        'quantity'        => $newItem['quantity'],
                        'remaining_stock' => $newItem['quantity'],
                        'price'           => $newItem['price'],
                        'total'           => $newItem['quantity'] * $newItem['price'],
                        'expire_date'     => $newItem['expire_date'],
                    ]);

                    Product::find($productId)?->increment('stock', $newItem['quantity']);
                }
            }

            $total = collect($validated['items'])->sum(fn($i) => $i['quantity'] * $i['price']);

            $purchase->update([
                'supplier_id' => $validated['supplier_id'],
                'date'        => $validated['date'],
                'notes'       => $validated['notes'] ?? $purchase->notes,
                'total'       => $total,
                'image'       => $validated['image'] ?? $purchase->image,
            ]);

            return $purchase;
        });

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث الفاتورة بنجاح',
            'data' => $purchase->load('items.product', 'supplier'),
        ], 200);
    }
    public function destroy($id)
    {
        $purchase = Purchase::find($id);
        $purchase->delete();
        return response()->json([
            "status" => true
        ]);
    }
}
