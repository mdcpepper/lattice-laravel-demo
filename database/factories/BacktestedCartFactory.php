<?php

namespace Database\Factories;

use App\Models\Backtest;
use App\Models\Cart;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BacktestedCart>
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
