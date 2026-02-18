<?php

namespace Database\Factories;

use App\Models\Backtest;
use App\Models\BacktestedCart;
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
            'backtest_id' => Backtest::factory(),
            'backtested_cart_id' => BacktestedCart::factory(),
            'cart_item_id' => CartItem::factory(),
            'product_id' => Product::factory(),
            'price' => 1000,
            'price_currency' => 'GBP',
            'offer_price' => 1000,
            'offer_price_currency' => 'GBP',
        ];
    }
}
