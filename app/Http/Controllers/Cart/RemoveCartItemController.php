<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Services\CartManager;
use Illuminate\Http\RedirectResponse;

class RemoveCartItemController extends Controller
{
    public function __invoke(
        int $item,
        CartManager $cartManager,
    ): RedirectResponse {
        $cartItem = CartItem::query()
            ->whereKey($item)
            ->whereHas('cart')
            ->firstOrFail();

        $cartManager->removeItem($cartItem);

        return redirect()->back();
    }
}
