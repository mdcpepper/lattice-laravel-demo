<?php

namespace Database\Factories\Promotions;

use App\Models\Promotions\Promotion;
use App\Models\Promotions\PromotionStack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Promotions\PromotionRedemption>
 */
class PromotionRedemptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promotion_id' => Promotion::factory(),
            'promotion_stack_id' => PromotionStack::factory(),
            'original_price' => 500,
            'original_price_currency' => 'GBP',
            'final_price' => 450,
            'final_price_currency' => 'GBP',
        ];
    }
}
