<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Services\CartManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RemoveCartItemController extends Controller
{
    public function __invoke(
        Request $request,
        int $item,
        CartManager $cartManager,
    ): RedirectResponse|View {
        $cartItem = CartItem::query()
            ->whereKey($item)
            ->whereHas('cart')
            ->firstOrFail();

        $cartManager->removeItem($cartItem);

        if ($request->hasHeader('HX-Request')) {
            return view('partials.cart-sidebar');
        }

        return redirect()->back();
    }
}
