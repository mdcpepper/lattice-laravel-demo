<?php

namespace Database\Factories;

use App\Enums\BacktestRunStatus;
use App\Models\PromotionStack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BacktestRun>
 */
class BacktestRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promotion_stack_id' => PromotionStack::factory(),
            'total_carts' => 1,
            'processed_carts' => 0,
            'status' => BacktestRunStatus::Pending,
        ];
    }
}
