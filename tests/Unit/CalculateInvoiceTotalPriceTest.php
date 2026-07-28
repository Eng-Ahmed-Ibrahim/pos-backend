<?php

namespace Tests\Unit;

use Tests\TestCase;
class CalculateInvoiceTotalPriceTest extends TestCase
{
    public function test_calculate_total_invoice_price()
    {
        $PurchaseService = new \App\Services\PurchaseService();
        $items = [
            ['quantity' => 2, 'price' => 10.00],
            ['quantity' => 3, 'price' => 5.00],
            ['quantity' => 1, 'price' => 20.00],
        ];

        $total = $PurchaseService->calculateTotalInvoicePrice($items);

        $this->assertEquals(55.00, $total);
    }
}
