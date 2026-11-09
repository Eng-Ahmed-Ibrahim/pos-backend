<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubCategoryController extends Controller
{
    public function index()
    {
        $categories = Helpers::cache_categories();
        $subCategories = Helpers::cache_sub_categories();
        $data=[
            'categories'=> $categories,
            'sub_categories'=>  $subCategories
        ];
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $subCategory = SubCategory::create($validator->validated());
        Helpers::delete_sub_categories();
        return response()->json([
            'status' => true,
            'message' => 'Sub category created successfully',
            'data' => $subCategory
        ]);
    }

    public function show($id)
    {
        $subCategory = SubCategory::with('category')->find($id);

        if (!$subCategory) {
            return response()->json([
                'status' => false,
                'message' => 'Sub category not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $subCategory
        ]);
    }

    public function update(Request $request, $id)
    {
        $subCategory = SubCategory::find($id);

        if (!$subCategory) {
            return response()->json([
                'status' => false,
                'message' => 'Sub category not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $subCategory->update($validator->validated());
        Helpers::delete_sub_categories();

        return response()->json([
            'status' => true,
            'message' => 'Sub category updated successfully',
            'data' => $subCategory
        ]);
    }

    public function destroy($id)
    {
        $subCategory = SubCategory::find($id);

        if (!$subCategory) {
            return response()->json([
                'status' => false,
                'message' => 'Sub category not found'
            ], 404);
        }
        if ($subCategory->products()->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن حذف التصنيف لوجود منتجات مرتبطة به.'
            ], 422);
        }

        $subCategory->delete();
        Helpers::delete_sub_categories();

        return response()->json([
            'status' => true,
            'message' => 'Sub category deleted successfully'
        ]);
    }
}