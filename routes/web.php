<?php

use App\Http\Controllers\IndexController;
use App\Http\Controllers\Products\IndexController as ProductsIndexController;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexController::class)->name('categories.index');

Route::get('/{slug}', ProductsIndexController::class)
    ->where('slug', '[A-Za-z0-9-]+')
    ->name('products.index');
