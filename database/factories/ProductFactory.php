<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'category_id' => Category::factory(),
            'stock' => fake()->numberBetween(0, 100),
            'price' => fake()->numberBetween(100, 10000),
            'image_url' => fake()->imageUrl(),
            'thumb_url' => fake()->imageUrl(150, 150),
        ];
    }
}
