<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $team = Team::factory();

        return [
            'team_id' => $team,
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'category_id' => Category::factory()->for($team),
            'stock' => fake()->numberBetween(0, 100),
            'price' => fake()->numberBetween(100, 10000),
            'image_url' => fake()->imageUrl(),
            'thumb_url' => fake()->imageUrl(150, 150),
        ];
    }
}
