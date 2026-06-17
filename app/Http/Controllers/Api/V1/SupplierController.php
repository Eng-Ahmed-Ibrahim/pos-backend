<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $suppliers = Supplier::latest()->get();

        return response()->json([
            'status' => true,
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Store a newly created resource.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:255',
            'phone'   => 'required|string|max:20',
            'address' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $supplier = Supplier::create($validator->validated());

        return response()->json([
            'status' => true,
            'message' => 'Supplier created successfully',
            'supplier' => $supplier,
        ], 201);
    }


    /**
     * Update the specified resource.
     */
    public function update(Request $request, string $id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json([
                'status' => false,
                'message' => 'Supplier not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:255',
            'phone'   => 'required|string|max:20',
            'address' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $supplier->update($validator->validated());

        return response()->json([
            'status' => true,
            'message' => 'Supplier updated successfully',
            'supplier' => $supplier,
        ]);
    }

    /**
     * Remove the specified resource.
     */
    public function destroy(string $id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json([
                'status' => false,
                'message' => 'Supplier not found',
            ], 404);
        }

        $supplier->delete();

        return response()->json([
            'status' => true,
            'message' => 'Supplier deleted successfully',
        ]);
    }
}