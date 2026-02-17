<?php

namespace Database\Factories;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\SimulatedCart;
use App\Models\SimulationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SimulatedCartItem>
 */
class SimulatedCartItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'simulation_run_id' => SimulationRun::factory(),
            'simulated_cart_id' => SimulatedCart::factory(),
            'cart_item_id' => CartItem::factory(),
            'product_id' => Product::factory(),
            'subtotal' => 1000,
            'subtotal_currency' => 'GBP',
            'total' => 1000,
            'total_currency' => 'GBP',
        ];
    }
}
