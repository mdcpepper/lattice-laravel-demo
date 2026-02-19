<?php

use App\Http\Controllers\Cart\AddToCartController;
use App\Http\Controllers\Cart\RemoveCartItemController;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\Products\IndexController as ProductsIndexController;
use App\Http\Middleware\GetCurrentTeam;
use Illuminate\Support\Facades\Route;

Route::middleware(GetCurrentTeam::class)->group(function (): void {
    Route::post('/cart/items', AddToCartController::class)->name(
        'cart.items.store',
    );

    Route::post('/cart/items/{item}/remove', RemoveCartItemController::class)
        ->whereNumber('item')
        ->name('cart.items.remove');

    Route::get('/', IndexController::class)->name('categories.index');

    Route::get('/{slug}', ProductsIndexController::class)
        ->where('slug', '[A-Za-z0-9-]+')
        ->name('products.index');
});
