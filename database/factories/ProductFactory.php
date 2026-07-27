<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
                $category = Category::factory()->create();
        $unit = Unit::factory()->create();

        return [
            'name' => fake()->word(),
            'barcode' => fake()->unique()->numberBetween(10000,99999),
            'price' => fake()->randomFloat(2, 10, 1000),
                 'category_id' => $category->id,
            'unit_id' => $unit->id,
        ];
    }
}