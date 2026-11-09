<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Requests\FinancialReportRequest;
use App\Services\InventoryReportService;
use App\Http\Controllers\Controller;
use Mpdf\Mpdf;

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
    public function downloadPdf(FinancialReportRequest $request)
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

        $data = array_merge($totals, [
            'purchases_value' => $movement['purchases_value'],
            'purchase_returns_value' => $movement['purchase_returns_value'],
            'waste_value' => $movement['waste_value'],
            'sold_cost_value' => $movement['sold_cost_value'],
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'],
        ]);

        // قراءة ملف الـ Blade وتحويله إلى HTML string
        $html = view('pdf.financial_report', $data)->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'L', // عرضي ليتسع للجدول براحة
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'autoArabic' => true,
            'autoLangToFont' => true,
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->SetTitle('التقرير المالي');

        $mpdf->WriteHTML($html);

        $pdfOutput = $mpdf->Output('', 'S');

        // إرجاع الملف للتحميل المباشر
        return response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="financial-report.pdf"',
        ]);
    }
}
