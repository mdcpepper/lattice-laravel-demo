<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Team;

class CartManager
{
    public function currentCart(Team $team, string $sessionId): Cart
    {
        return Cart::firstOrCreate(
            ['team_id' => $team->id, 'session_id' => $sessionId],
        );
    }

    public function addItem(Cart $cart, Product $product): CartItem
    {
        return $cart->items()->create(['product_id' => $product->id]);
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }
}
