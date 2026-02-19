<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Models\Product;
use App\Services\CartManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AddToCartController extends Controller
{
    public function __invoke(
        AddToCartRequest $request,
        CartManager $cartManager,
    ): RedirectResponse|View {
        $product = Product::query()
            ->whereKey($request->integer('product'))
            ->firstOrFail();

        $cart = $cartManager->currentCartForCurrentTeam($request->session());

        $cartManager->addItem($cart, $product);

        if ($request->hasHeader('HX-Request')) {
            return view('partials.cart-sidebar');
        }

        return redirect()->back();
    }
}
