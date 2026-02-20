<?php

namespace Database\Factories\Backtests;

use App\Models\Backtests\Backtest;
use App\Models\Cart\Cart;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Backtests\BacktestedCart>
 */
class BacktestedCartFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'backtest_id' => Backtest::factory(),
            'cart_id' => Cart::factory(),
            'team_id' => Team::factory(),
            'email' => null,
            'customer_id' => null,
            'subtotal' => 1000,
            'subtotal_currency' => 'GBP',
            'total' => 1000,
            'total_currency' => 'GBP',
        ];
    }
}
