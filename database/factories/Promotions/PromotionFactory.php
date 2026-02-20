<?php

namespace Database\Factories\Promotions;

use App\Enums\SimpleDiscountKind;
use App\Models\Promotions\DirectDiscountPromotion;
use App\Models\Promotions\SimpleDiscount;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Promotions\Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $discount = SimpleDiscount::query()->create([
            'kind' => SimpleDiscountKind::PercentageOff,
            'percentage' => 10.0,
        ]);

        $promotionable = DirectDiscountPromotion::query()->create([
            'simple_discount_id' => $discount->id,
        ]);

        return [
            'team_id' => Team::factory(),
            'name' => $this->faker->words(3, true),
            'promotionable_type' => $promotionable->getMorphClass(),
            'promotionable_id' => $promotionable->id,
        ];
    }
}
