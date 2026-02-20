<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart\Cart;
use App\Models\Product;
use App\Models\Promotions\Promotion;
use Illuminate\Support\Collection;

readonly class ProductPromotionResolver
{
    public function __construct(
        private ProductQualificationChecker $checker,
    ) {}

    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, Collection<int, Promotion>>
     */
    public function decorate(Collection $products, ?Cart $cart = null): array
    {
        $stackPromotionIds = $this->resolveStackPromotionIds($cart);

        return $products->mapWithKeys(
            fn (Product $product): array => [
                $product->id => $this->checker
                    ->qualifyingPromotions($product, (int) $product->team_id)
                    ->when(
                        $stackPromotionIds !== null,
                        fn (Collection $promotions): Collection => $promotions->filter(
                            fn (Promotion $promotion): bool => $stackPromotionIds->contains($promotion->id),
                        )->values(),
                    ),
            ],
        )->all();
    }

    /**
     * Returns the promotion IDs available in the cart's stack, an empty
     * collection if the cart has no stack, or null if no cart was provided
     * (meaning no filtering should be applied).
     *
     * @return Collection<int, int>|null
     */
    private function resolveStackPromotionIds(?Cart $cart): ?Collection
    {
        if ($cart === null) {
            return null;
        }

        if ($cart->promotion_stack_id === null) {
            return collect();
        }

        return $cart->promotionStack()
            ->with('layers.promotions')
            ->first()
            ?->layers
            ->flatMap(fn ($layer) => $layer->promotions->pluck('id'))
            ->unique()
            ->values()
            ?? collect();
    }
}
