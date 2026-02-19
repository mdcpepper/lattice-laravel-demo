<?php

namespace App\ViewModels\Products;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Spatie\ViewModels\ViewModel;

class IndexViewModel extends ViewModel
{
    /**
     * @param  Collection<int, Product>|null  $products
     */
    public function __construct(
        private string $slug,
        private ?Category $category = null,
        private ?Collection $products = null,
    ) {}

    public function category(): Category
    {
        if ($this->category === null) {
            $this->category = Category::query()
                ->where('slug', $this->slug)
                ->firstOrFail();
        }

        return $this->category;
    }

    /**
     * @return Collection<int, Product>
     */
    public function products(): Collection
    {
        if ($this->products === null) {
            $this->products = $this->category()
                ->products()
                ->orderBy('name')
                ->get();
        }

        return $this->products;
    }
}
