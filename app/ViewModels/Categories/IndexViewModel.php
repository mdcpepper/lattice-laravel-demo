<?php

namespace App\ViewModels\Categories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
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
                ->with([
                    'mainProduct' => function (Relation $relation): void {
                        $relation->select([
                            'products.id',
                            'products.name',
                            'products.thumb_url',
                            'products.image_url',
                        ]);
                    },
                    'highestPricedProduct' => function (
                        Relation $relation,
                    ): void {
                        $relation->select([
                            'products.id',
                            'products.category_id',
                            'products.name',
                            'products.thumb_url',
                            'products.image_url',
                        ]);
                    },
                ])
                ->orderBy('name')
                ->get();
        }

        return $this->categories;
    }
}
