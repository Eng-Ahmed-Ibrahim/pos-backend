<?php

namespace Database\Factories;

use App\Models\Purchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    public function definition(): array
    {
        $supplier = \App\Models\Supplier::factory()->create();
        return [
            'supplier_id'=>$supplier->id,
            'total'=>$this->faker->randomFloat(2, 10, 100),
            'image'=>$this->faker->imageUrl(),
            'date'=>$this->faker->date(),
        ];
    }
}
