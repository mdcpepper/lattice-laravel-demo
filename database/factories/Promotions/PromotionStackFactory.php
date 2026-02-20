<?php

namespace Database\Factories\Promotions;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Promotions\PromotionStack>
 */
class PromotionStackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->words(3, true),
            'root_layer_reference' => null,
            'active_from' => null,
            'active_to' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(
            fn (array $attributes): array => [
                'active_from' => now()->toDateString(),
                'active_to' => null,
            ],
        );
    }
}
