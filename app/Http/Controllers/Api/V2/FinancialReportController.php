<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Requests\FinancialReportRequest;
use App\Services\InventoryReportService;
use App\Http\Controllers\Controller;

class FinancialReportController extends Controller
{
    public function __construct(
        protected InventoryReportService $reportService
    ) {}
    public function summary(FinancialReportRequest $request)
    {
        $validated = $request->validated();

        $totals = $this->reportService->getTotalStockValue(
            $validated['start_at'],
            $validated['end_at']
        );

        $movement = $this->reportService->getInventoryReport(
            $validated['start_at'],
            $validated['end_at']
        );

        return response()->json([
            'data' => array_merge($totals, [
                'purchases_value' => $movement['purchases_value'],
                'purchase_returns_value' => $movement['purchase_returns_value'],
                'waste_value' => $movement['waste_value'],
                'sold_cost_value' => $movement['sold_cost_value'],
            ]),
        ]);
    }
    public function products(FinancialReportRequest $request)
    {
        $validated = $request->validated();

        $products = $this->reportService->getProductsValueBetween(
            $validated['start_at'],
            $validated['end_at']
        );

        return response()->json([
            'data' => $products,
        ]);
    }
}