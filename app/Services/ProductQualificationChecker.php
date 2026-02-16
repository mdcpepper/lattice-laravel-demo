<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\Promotion;
use App\Services\PromotionQualification\PromotionQualificationStrategy;
use Illuminate\Support\Collection;

class ProductQualificationChecker
{
    /** @var Collection<int, Promotion>|null */
    private ?Collection $promotions = null;

    /**
     * @param  array<int, PromotionQualificationStrategy>  $promotionQualificationStrategies
     */
    public function __construct(
        private readonly array $promotionQualificationStrategies,
    ) {}

    /** @return string[] */
    public function qualifyingPromotionNames(Product $product): array
    {
        $productTagNames = $this->productTagNames($product);

        return $this->getPromotions()
            ->filter(
                fn (Promotion $promotion): bool => $this->qualifiesForPromotion(
                    $productTagNames,
                    $promotion,
                ),
            )
            ->pluck('name')
            ->toArray();
    }

    /** @return Collection<int, Promotion> */
    private function getPromotions(): Collection
    {
        if ($this->promotions === null) {
            $this->promotions = Promotion::withGraph()->get();
        }

        return $this->promotions;
    }

    /** @return string[] */
    private function productTagNames(Product $product): array
    {
        return $product->tags
            ->map(fn ($tag): string => strtolower((string) $tag->name))
            ->values()
            ->all();
    }

    /** @param string[] $productTagNames */
    private function qualifiesForPromotion(
        array $productTagNames,
        Promotion $promotion,
    ): bool {
        $strategy = collect($this->promotionQualificationStrategies)->first(
            fn (
                PromotionQualificationStrategy $strategy,
            ): bool => $strategy->supports($promotion),
        );

        if (! $strategy instanceof PromotionQualificationStrategy) {
            return false;
        }

        return $strategy->qualifies($promotion, $productTagNames);
    }
}
