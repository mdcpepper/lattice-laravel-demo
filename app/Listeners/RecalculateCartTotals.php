<?php

namespace App\Listeners;

use App\Events\CartRecalculationRequested;
use App\Models\Cart\Cart;
use App\Services\CartRecalculator;

readonly class RecalculateCartTotals
{
    public function __construct(
        private CartRecalculator $cartRecalculator,
    ) {}

    public function handle(CartRecalculationRequested $event): void
    {
        $cart = Cart::query()->find($event->cartId);

        if (! $cart instanceof Cart) {
            return;
        }

        $this->cartRecalculator->recalculate($cart);
    }
}
