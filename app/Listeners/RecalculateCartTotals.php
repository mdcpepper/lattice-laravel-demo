<?php

namespace App\Listeners;

use App\Events\CartRecalculationRequested;
use App\Models\Cart;
use App\Services\CartRecalculator;

class RecalculateCartTotals
{
    public function __construct(
        private readonly CartRecalculator $cartRecalculator,
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
