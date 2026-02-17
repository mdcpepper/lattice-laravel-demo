<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\PromotionStack;
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

        $activeStack = PromotionStack::activeForTeam($team->id);

        $cart = Cart::query()->create([
            'team_id' => $team->id,
            'promotion_stack_id' => $activeStack?->id,
        ]);

        $session->put('cart_ulid', $cart->ulid);

        return $cart;
    }

    public function addItem(Cart $cart, Product $product): CartItem
    {
        $amount = $product->price->getAmount();
        $currency = $product->price->getCurrency()->getCode();

        return $cart->items()->create([
            'product_id' => $product->id,
            'subtotal' => $amount,
            'subtotal_currency' => $currency,
            'total' => $amount,
            'total_currency' => $currency,
        ]);
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }
}
