<?php

namespace Database\Factories\Promotions;

use App\Enums\PromotionLayerOutputMode;
use App\Enums\PromotionLayerOutputTargetMode;
use App\Models\Promotions\PromotionLayer;
use App\Models\Promotions\PromotionStack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<PromotionLayer>
 */
class PromotionLayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promotion_stack_id' => PromotionStack::factory(),
            'reference' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
            'output_mode' => PromotionLayerOutputMode::PassThrough->value,
            'participating_output_mode' => PromotionLayerOutputTargetMode::PassThrough->value,
            'participating_output_layer_id' => null,
            'non_participating_output_mode' => PromotionLayerOutputTargetMode::PassThrough->value,
            'non_participating_output_layer_id' => null,
        ];
    }
}
