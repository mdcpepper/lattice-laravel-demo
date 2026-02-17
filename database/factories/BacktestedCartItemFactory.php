<?php

namespace Database\Factories;

use App\Models\BacktestedCart;
use App\Models\BacktestRun;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BacktestedCartItem>
 */
class BacktestedCartItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'backtest_id' => BacktestRun::factory(),
            'backtested_cart_id' => BacktestedCart::factory(),
            'cart_item_id' => CartItem::factory(),
            'product_id' => Product::factory(),
            'subtotal' => 1000,
            'subtotal_currency' => 'GBP',
            'total' => 1000,
            'total_currency' => 'GBP',
        ];
    }
}
