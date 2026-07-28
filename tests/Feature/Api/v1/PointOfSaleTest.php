<?php

namespace Tests\Feature\Api\v1;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PointOfSaleTest extends TestCase
{
    
    use RefreshDatabase;
    public function test_sale_without_authenticated()
    {
        $response = $this->postJson('api/v1/sales');
        $response->assertStatus(401);
    }
    public function test_sale_with_authenticated_with_empty_payload()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('api/v1/sales');
        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'errors',
        ]);
    }
    public function test_sale_with_valid_products_has_no_stock()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        $product = Product::factory()->create();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('api/v1/sales', [
            'items' => [["product_id" => $product->id, 'quantity' => 1, 'price' => 15]]
        ]);
        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'status'
        ]);
    }
    public function test_sale_with_valid_product_has_stock()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        $purchaseItem = \App\Models\PurchaseItems::factory()->create([
            'quantity' => 10,
            'remaining_stock' => 10,
            'price' => 18,
        ]);
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('api/v1/sales', [
            'items' => [["product_id" => $purchaseItem->product_id, 'quantity' => 1, 'price' => 15]]
        ]);
        $this->assertDatabaseHas('purchase_items', [
            'product_id' => $purchaseItem->product_id,
            'remaining_stock' => 9,
        ]);
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'status',
            "message" 
        ]);
    }
    public function test_sale_with_valid_product_has_remaining_stock_0()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        $purchaseItem = \App\Models\PurchaseItems::factory()->create([
            'quantity' => 10,
            'remaining_stock' => 0,
            'price' => 18,
        ]);
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('api/v1/sales', [
            'items' => [["product_id" => $purchaseItem->product_id, 'quantity' => 1, 'price' => 15]]
        ]);
        $response->assertStatus(422);
        $response->assertJsonStructure([
            'status',
            "message" 
        ]);
    }
}
