<?php

namespace App\Http\Controllers;

use App\ViewModels\Categories\IndexViewModel;

class IndexController extends Controller
{
    public function __invoke(): IndexViewModel
    {
        return new IndexViewModel()->view('categories.index');
    }
}
