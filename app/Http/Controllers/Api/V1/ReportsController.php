<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItems;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportsController extends Controller
{
    public function index(Request $request)
    {

        $query = SaleItems::query();

        $type = $request->filled('filter_type') ? $request->filter_type : 'daily';
        if ($type) {
            switch ($type) {
                case 'daily':
                    $query->whereDate('created_at', Carbon::today());
                    break;
                case 'weekly':
                    $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                    break;
                case 'monthly':
                    $query->whereMonth('created_at', Carbon::now()->month)
                        ->whereYear('created_at', Carbon::now()->year);
                    break;
                case 'custom':
                    $query->when($request->filled('from_date'), function ($q) use ($request) {
                        return $q->whereDate('created_at', '>=', $request->from_date);
                    });
                    $query->when($request->filled('to_date'), function ($q) use ($request) {
                        return $q->whereDate('created_at', '<=', $request->to_date);
                    });
                    break;
            }
        }


        $grandTotal = (float) $query->sum('total');

        $reports = (clone $query)
            ->with('product:id,name')
            ->selectRaw('
                    product_id,
                    SUM(quantity) as total_quantity,
                    SUM(total) as total_sales
                ')
                ->orderBy("total_quantity","DESC")
            ->groupBy('product_id')
            ->paginate(15);

        return response()->json([
            "status"     => true,
            'reports'    => $reports->items(),
            // 'reports'    => [],
            'summary'    => [
                'grand_total'       => $grandTotal,
                'grand_amount_paid' => $grandTotal,
            ],
            'pagination' => [
                'current_page' => $reports->currentPage(),
                'last_page'    => $reports->lastPage(),
                'per_page'     => $reports->perPage(),
                'total'        => $reports->total(),
            ],
        ]);
    }
}
