<?php

namespace App\Http\Controllers\Api\V1;

use Exception;
use App\Models\Sale;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\PurchaseItems;
use App\Services\SalesService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use Illuminate\Support\Facades\Validator;

class SaleController extends Controller
{

    public function __construct(private SalesService $salesService) {}
    public function index()
    {
        $sales = Sale::with(['items', 'items.product'])->get();
        return response()->json([
            "status" => true,
            "sales" => $sales
        ]);
    }
    public function store(StoreSaleRequest  $request)
    {
        $validated = $request->validated();
        $user = $request->user();
        try {
            $sale = DB::transaction(function () use ($validated, $user) {
                return $this->salesService->create_sale($validated, $user);
            });
            return response()->json([
                'invoice_id'=>$sale->id,
                'status' => true,
                'message' => 'تم إتمام عملية البيع بنجاح',
            ], 201);
        } catch (\Throwable $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 422);
        }



    }

    public function show($id)
    {
        $sale = Sale::with('items.product')->findOrFail($id);

        return response()->json([
            'status' => true,
            'sale' => $sale,
        ]);
    }
}
