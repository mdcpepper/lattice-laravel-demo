<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Models\Product;
use App\Services\CartManager;
use Illuminate\Http\RedirectResponse;

class AddToCartController extends Controller
{
    public function __invoke(
        AddToCartRequest $request,
        CartManager $cartManager,
    ): RedirectResponse {
        $product = Product::query()
            ->whereKey($request->integer('product'))
            ->firstOrFail();

        $cart = $cartManager->currentCartForCurrentTeam($request->session());

        $cartManager->addItem($cart, $product);

        return redirect()->back();
    }
}
