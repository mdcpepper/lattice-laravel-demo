<?php

declare(strict_types=1);

namespace App\Services\PromotionDiscount;

use App\Models\Promotions\Promotion;

class PromotionDiscountFormatter
{
    /**
     * @param  array<int, PromotionDiscountStrategy>  $promotionDiscountStrategies
     */
    public function __construct(
        private readonly array $promotionDiscountStrategies,
    ) {}

    public function format(Promotion $promotion): ?string
    {
        $strategy = collect($this->promotionDiscountStrategies)->first(
            fn (
                PromotionDiscountStrategy $strategy,
            ): bool => $strategy->supports($promotion),
        );

        if (! $strategy instanceof PromotionDiscountStrategy) {
            return null;
        }

        return $strategy->format($promotion);
    }
}
