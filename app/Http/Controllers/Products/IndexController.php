<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\ViewModels\Products\IndexViewModel;

class IndexController extends Controller
{
    public function __invoke(string $slug): IndexViewModel
    {
        return new IndexViewModel($slug)->view('products.index');
    }
}
