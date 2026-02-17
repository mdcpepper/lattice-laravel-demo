<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\SimulationRun;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SimulatedCart>
 */
class SimulatedCartFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'simulation_run_id' => SimulationRun::factory(),
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
