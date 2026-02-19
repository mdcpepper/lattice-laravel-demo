<?php

namespace App\Services;

use App\Events\CartRecalculationRequested;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\PromotionStack;
use App\Models\Team;
use Illuminate\Contracts\Session\Session;

class CartManager
{
    public function currentCartForCurrentTeam(Session $session): Cart
    {
        return $this->currentCart(app(CurrentTeam::class)->team, $session);
    }

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

    public function addItem(
        Cart $cart,
        Product $product,
        bool $requestRecalculation = true,
    ): CartItem {
        $amount = $product->price->getAmount();
        $currency = $product->price->getCurrency()->getCode();

        $item = $cart->items()->create([
            'product_id' => $product->id,
            'price' => $amount,
            'price_currency' => $currency,
            'offer_price' => $amount,
            'offer_price_currency' => $currency,
        ]);

        if ($requestRecalculation) {
            $this->requestRecalculation($cart);
        }

        return $item;
    }

    public function removeItem(
        CartItem $item,
        bool $requestRecalculation = true,
    ): void {
        $cartId = (int) $item->cart_id;

        $item->delete();

        if ($requestRecalculation) {
            CartRecalculationRequested::dispatch($cartId);
        }
    }

    public function requestRecalculation(Cart $cart): void
    {
        CartRecalculationRequested::dispatch($cart->id);
    }
}
