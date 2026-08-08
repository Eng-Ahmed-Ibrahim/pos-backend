<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Helpers\Helpers;
use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\StoreProductRequest;

class ProductsController extends Controller
{

    public function cached_product()
    {
        $products = Helpers::cache_products();
        $data = ['products' => $products];
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('barcode', 'like', "%{$request->search}%");
            });
        }
$products = $query
    ->with(['category', 'sub_category', 'unit'])
    ->withSum([
        'purchaseItems as purchase_items_sum_remaining_stock' => function ($q) {
            $q->whereHas('purchase', function ($q) {
                $q->whereNotIn('status', ['cancelled', 'rejected']);
            });
        }
    ], 'remaining_stock')
    ->orderBy('price', 'DESC')
    ->paginate(15);

        $categories = Helpers::cache_categories();
        $sub_categories = Helpers::cache_sub_categories();
        $units = Helpers::cache_units();
        $data = [
            'products' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'per_page'     => $products->perPage(),
                'total'        => $products->total(),
            ],
            'categories' => $categories,
            'units' => $units,
            'sub_categories' => $sub_categories,
        ];
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    /**
     * POST /api/v1/products
     */
    public function store(StoreProductRequest $request)
    {
        $product = Product::create($request->validated());
        Helpers::delete_products();
        Helpers::delete_all_products();
        return response()->json([
            'status' => true,
            'message' => 'Product created successfully',
            'data' => $product
        ]);
    }

    /**
     * GET /api/v1/products/{id}
     */
    public function show($id)
    {
        $product = Product::with(['category', 'subCategory'])->find($id);

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $product
        ]);
    }

    /**
     * PUT /api/v1/products/{id}
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'barcode' => 'required|string|unique:products,barcode,' . $id,
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'nullable|exists:sub_categories,id',
            'minimum_stock' => "nullable|integer",
            'price' => 'nullable|decimal:0,3',
            'unit_id' => 'integer|exists:units,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $product->update($validator->validated());
        Helpers::delete_products();
        Helpers::delete_all_products();

        return response()->json([
            'status' => true,
            'message' => 'Product updated successfully',
            'data' => $product
        ]);
    }

    /**
     * DELETE /api/v1/products/{id}
     */
    public function destroy($id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        }
        if($product->purchaseItems()->exist()){
            return response()->json([
                "message"=>"لا يمكن حذف هذا المنتج",
                "status"=>false
            ],422);
        }

        $product->delete();
        Helpers::delete_products();

        return response()->json([
            'status' => true,
            'message' => 'Product deleted successfully'
        ]);
    }
}
