<?php

namespace App\ViewModels\Categories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Spatie\ViewModels\ViewModel;

class IndexViewModel extends ViewModel
{
    /**
     * @param  Collection<int, Category>|null  $categories
     */
    public function __construct(private ?Collection $categories = null) {}

    /**
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        if ($this->categories === null) {
            $this->categories = Category::query()
                ->with(['mainProduct', 'highestPricedProduct'])
                ->orderBy('name')
                ->get();
        }

        return $this->categories;
    }
}
