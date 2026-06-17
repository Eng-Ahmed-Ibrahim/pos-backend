<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductsController extends Controller
{
    /**
     * GET /api/v1/products
     */
    public function index()
    {
        $products = Product::with(['category', 'sub_category'])->latest()->get();
        $categories = Category::latest()->get();
        $sub_categories = SubCategory::latest()->get();
        $data = [
            'products' => $products,
            'categories' => $categories,
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
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'barcode' => 'required|string|unique:products,barcode',
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'nullable|exists:sub_categories,id',
            'minimum_stock' => "nullable|integer",
            "price" => "nullable|integer"
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $product = Product::create($validator->validated());

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
            "price" => "nullable|integer"
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $product->update($validator->validated());

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

        $product->delete();

        return response()->json([
            'status' => true,
            'message' => 'Product deleted successfully'
        ]);
    }
}
