<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\Promotion;
use App\Services\PromotionQualification\PromotionQualificationStrategy;
use Illuminate\Support\Collection;

class ProductQualificationChecker
{
    /** @var array<int|string, Collection<int, Promotion>> */
    private array $promotionsByScope = [];

    /**
     * @param  array<int, PromotionQualificationStrategy>  $promotionQualificationStrategies
     */
    public function __construct(
        private readonly array $promotionQualificationStrategies,
    ) {}

    /**
     * @return string[]
     */
    public function qualifyingPromotionNames(
        Product $product,
        ?int $teamId = null,
    ): array {
        $productTagNames = $this->productTagNames($product);

        return $this->getPromotions($teamId)
            ->filter(
                fn (Promotion $promotion): bool => $this->qualifiesForPromotion(
                    $productTagNames,
                    $promotion,
                ),
            )
            ->pluck('name')
            ->toArray();
    }

    /**
     * @param  Collection<int, Promotion>  $promotions
     */
    public function qualifiesForAnyPromotion(
        Product $product,
        Collection $promotions,
    ): bool {
        $productTagNames = $this->productTagNames($product);

        return $promotions->contains(
            fn (Promotion $promotion): bool => $this->qualifiesForPromotion(
                $productTagNames,
                $promotion,
            ),
        );
    }

    public function hasAnyQualifyingPromotion(
        Product $product,
        ?int $teamId = null,
    ): bool {
        return $this->qualifiesForAnyPromotion(
            $product,
            $this->getPromotions($teamId),
        );
    }

    /**
     * @return Collection<int, Promotion>
     */
    private function getPromotions(?int $teamId = null): Collection
    {
        $scopeKey = $teamId ?? 'all';

        if (! array_key_exists($scopeKey, $this->promotionsByScope)) {
            $promotionQuery = Promotion::query();

            if ($teamId !== null) {
                $promotionQuery->where('team_id', $teamId);
            }

            $this->promotionsByScope[$scopeKey] = $promotionQuery
                ->withGraph()
                ->get();
        }

        return $this->promotionsByScope[$scopeKey];
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
