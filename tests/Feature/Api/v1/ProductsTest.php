<?php

namespace Tests\Feature\Api\v1;

use App\Models\Product;
use App\Models\PurchaseItems;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductsTest extends TestCase
{
    use RefreshDatabase;
    public function test_get_products_without_authentication()
    {
        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(401);
    }

    public function test_get_empty_products_with_authentication()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/products');

        $response->assertStatus(200);

        $response->assertJsonStructure(([
            'status',
            'data' => [
                'products',
                'pagination',
                'categories',
                'units',
                'sub_categories',
            ]
        ]));
        $response->assertJsonCount(0, 'data.products');
    }
    public function test_get_products_with_authentication()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        $products = Product::factory()->count(100)->create();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/products');

        $response->assertStatus(200);

        $response->assertJsonStructure(([
            'status',
            'data' => [
                'products',
                'pagination',
                'categories',
                'units',
                'sub_categories',
            ]
        ]));
        $response->assertJsonCount(15, 'data.products');
    }
    public function test_get_products_with_authentication_and_search()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        $products = Product::factory()->count(100)->create();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/products?search=non-existing-product');
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data.products');
    }
    public function test_get_products_with_authentication_and_search_existing_product()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        $products = Product::factory()->count(100)->create();
        $existingProduct = $products->first();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/products?search=' . $existingProduct->barcode);
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.products');
    }
    public function test_get_products_with_authentication_and_pagination()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        $products = Product::factory()->count(100)->create();
        $existingProduct = $products->first();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/products?page=2');
        $response->assertStatus(200);
        $response->assertJsonCount(15, 'data.products');
    }
    public function test_delete_product_has_not_linked_to_purchase_items()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/products/{$product->id}");
        $response->assertStatus(200);
        $this->assertSoftDeleted('products', [
            'id' => $product->id,
        ]);
    }

    public function test_delete_product_has_linked_to_purchase_items()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $purchaseItem = PurchaseItems::factory()->create([
            'quantity' => 10,
            'remaining_stock' => 10,
            'price' => 18,
            'product_id' => $product->id
        ]);
        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/products/{$product->id}");
        $response->assertStatus(422);
        $this->assertNotSoftDeleted("products",[
            "id"=>$product->id,
        ]);
    }
}
