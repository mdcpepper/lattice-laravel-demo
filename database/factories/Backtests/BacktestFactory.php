<?php

namespace Database\Factories\Backtests;

use App\Enums\BacktestStatus;
use App\Models\Backtests\Backtest;
use App\Models\Promotions\PromotionStack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Backtest>
 */
class BacktestFactory extends Factory
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
            'status' => BacktestStatus::Pending,
        ];
    }
}
