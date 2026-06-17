<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * GET /api/v1/categories
     */
    public function index()
    {
        return response()->json([
            'status' => true,
            'data' => Category::latest()->get()
        ]);
    }

    /**
     * POST /api/v1/categories
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpg,png,jpeg'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        $data['image'] = 'uploads/default_image.png';
        // handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/categories'), $imageName);

            $data['image'] = 'uploads/categories/' . $imageName;
        }

        $category = Category::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Category created successfully',
            'data' => $category
        ]);
    }

    /**
     * GET /api/v1/categories/{id}
     */
    public function show($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $category
        ]);
    }

    /**
     * PUT /api/v1/categories/{id}
     */
    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpg,png,jpeg'

        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        if ($request->hasFile('image')) {
            if ($category->image && file_exists(public_path($category->image))) {
                unlink(public_path($category->image));
            }
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/categories'), $imageName);

            $data['image'] = 'uploads/categories/' . $imageName;
        }

        $category->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

    /**
     * DELETE /api/v1/categories/{id}
     */
    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found'
            ], 404);
        }
        if ($category->products()->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن حذف التصنيف لوجود منتجات مرتبطة به.'
            ], 422);
        }

        $category->delete();

        return response()->json([
            'status' => true,
            'message' => 'Category deleted successfully'
        ]);
    }
}
