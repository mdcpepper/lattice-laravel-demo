<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Services\CartManager;
use App\Services\ProductPromotionResolver;
use App\ViewModels\Products\IndexViewModel;
use Illuminate\Contracts\Session\Session;

class IndexController extends Controller
{
    public function __invoke(
        string $slug,
        ProductPromotionResolver $resolver,
        CartManager $cartManager,
        Session $session,
    ): IndexViewModel {
        $cart = $cartManager->currentCartForCurrentTeam($session);

        return new IndexViewModel($slug, $resolver, $cart)->view('products.index');
    }
}
