<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Models\Unit;

class UnitController extends Controller
{
    public function index()
    {
        $units = Helpers::cache_units();

        return response()->json([
            'status' => true,
            'data' => [
                'units' => $units,
            ],
        ]);
    }

    public function store(StoreUnitRequest $request)
    {
        $unit = Unit::create($request->validated());
        Helpers::delete_units();
        return response()->json([
            'status' => true,
            'message' => 'تم إضافة الوحدة بنجاح',
            'data' => $unit,
        ], 201);
    }

    public function update(UpdateUnitRequest $request, Unit $unit)
    {
        $unit->update($request->validated());
        Helpers::delete_units();

        return response()->json([
            'status' => true,
            'message' => 'تم تعديل الوحدة بنجاح',
            'data' => $unit,
        ]);
    }

    public function destroy(Unit $unit)
    {
        try {
            $unit->delete();
            Helpers::delete_units();
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن حذف الوحدة لارتباطها ببيانات أخرى',
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'تم حذف الوحدة بنجاح',
        ]);
    }
}
