<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => ProductCategory::factory(),
            'brand_id' => null,
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 100000),
            'sku' => strtoupper(Str::random(3)).'-'.fake()->unique()->numberBetween(10000, 99999),
            'barcode' => null,
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 500, 50000),
            'cost_price' => null,
            'compare_at_price' => null,
            'tax_rate' => null,
            'is_active' => true,
            'is_featured' => false,
            'requires_prescription' => false,
            'weight' => null,
            'sort_order' => 0,
        ];
    }
}
