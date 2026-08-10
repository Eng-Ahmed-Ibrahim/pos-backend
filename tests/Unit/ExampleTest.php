<?php

namespace Tests\Unit;

use App\Services\PurchaseService;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
public function test_create_invoice_calculates_total()
{
    $service = new PurchaseService();

    $result = $service->createInvoice([
        'supplier_id' => 1,
        'date' => '2026-01-01',
        'items' => [
            ['product_id' => 1, 'quantity' => 2, 'price' => 10, 'expire_date' => null],
        ],
    ]);

    $this->assertEquals(20.0, $result->total);
}
}
