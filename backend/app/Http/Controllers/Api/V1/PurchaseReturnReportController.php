<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\PurchaseReturn;
use Illuminate\Http\Request;
 
class PurchaseReturnReportController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseReturn::with(['supplier', 'user:id,name','items.product']);

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $totalSum = (clone $query)->sum('total_amount');
        $totalCount = (clone $query)->count();

        $returns = $query->latest()->get(); 
        $suppliers = Helpers::cache_suppliers();

        return response()->json([
            'status' => true,
            'data' => [
                'returns' => $returns,
                'total_amount' => $totalSum,
                'total_count' => $totalCount,
                'suppliers' => $suppliers
            ]
        ]);
    }
}