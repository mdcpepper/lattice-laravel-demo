<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Contracts\Session\Session;

class CartManager
{
    public function currentCart(Team $team, Session $session): Cart
    {
        $ulid = $session->get('cart_ulid');

        if ($ulid !== null) {
            $cart = Cart::query()
                ->where('ulid', $ulid)
                ->where('team_id', $team->id)
                ->first();

            if ($cart instanceof Cart) {
                return $cart;
            }
        }

        $cart = Cart::query()->create(['team_id' => $team->id]);
        $session->put('cart_ulid', $cart->ulid);

        return $cart;
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
