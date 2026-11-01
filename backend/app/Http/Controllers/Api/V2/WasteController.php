<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWasteRequest;
use App\Models\Wastes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\StockDeductionService;

class WasteController extends Controller
{
    public function __construct(
        private StockDeductionService $stockDeductionService
    ) {}

    public function index()
    {
        $wastes = Wastes::with('itemBatches', 'product', 'user')
            ->latest()
            ->paginate(20);

        return response()->json(['status' => true, 'data' => $wastes]);
    }
    public function store(StoreWasteRequest $request)
    {
        $validated = $request->validated();
        $user = $request->user();
        $wastes = DB::transaction(function () use ($validated, $user) {
            $created = collect();
            foreach ($validated['items'] as $item) {
                $deductions = $this->stockDeductionService->deduct(
                    $item['product_id'],
                    $item['quantity']
                );

                $waste = Wastes::create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'reason' => $item['reason'] ?? null,
                    'user_id' => $user->id,
                ]);

                foreach ($deductions as $deduction) {
                    $waste->itemBatches()->create($deduction);
                }
                $created->push($waste);
            }
            return $created;
        });

        return response()->json([
            'status'=>true,
            'message' => 'تم تسجيل الهالك بنجاح',
            'data' => $wastes,
        ], 201);
    }
}
