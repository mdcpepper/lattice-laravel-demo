<?php

namespace App\ViewModels\Products;

use App\Models\Cart\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotions\Promotion;
use App\Services\ProductPromotionResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Spatie\ViewModels\ViewModel;

class IndexViewModel extends ViewModel
{
    private ?Collection $cachedProducts = null;

    public function __construct(
        private string $slug,
        private readonly ProductPromotionResolver $resolver,
        private readonly Cart $cart,
        private ?Category $category = null,
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
        if ($this->cachedProducts === null) {
            $this->cachedProducts = $this->category()
                ->products()
                ->with('tags')
                ->orderBy('name')
                ->get();
        }

        return $this->cachedProducts;
    }

    /**
     * @return array<int, BaseCollection<int, Promotion>>
     */
    public function promotionsByProductId(): array
    {
        return $this->resolver->decorate($this->products(), $this->cart);
    }
}
