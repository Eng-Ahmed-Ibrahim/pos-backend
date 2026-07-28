<?php

namespace Database\Factories;

use App\Models\PurchaseItems;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseItems>
 */
class PurchaseItemsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $purchase = \App\Models\Purchase::factory()->create();
        return [
            'purchase_id'=>$purchase->id,
            'product_id'=>\App\Models\Product::factory()->create()->id,
            'quantity'=>100,
            'price'=>18.00,
            'expire_date'=>$this->faker->date(),
        ];
    }
}
