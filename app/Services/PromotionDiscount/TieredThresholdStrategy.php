<?php

declare(strict_types=1);

namespace App\Services\PromotionDiscount;

use App\Models\Promotion;
use App\Models\TieredThresholdPromotion;

class TieredThresholdStrategy implements PromotionDiscountStrategy
{
    public function supports(Promotion $promotion): bool
    {
        return $promotion->promotionable instanceof TieredThresholdPromotion;
    }

    public function format(Promotion $promotion): ?string
    {
        $promotionable = $promotion->promotionable;

        if (! $promotionable instanceof TieredThresholdPromotion) {
            return null;
        }

        $tierCount = $promotionable->tiers->count();
        $label = $tierCount === 1 ? 'tier' : 'tiers';

        return sprintf('Tiered Threshold: %d %s', $tierCount, $label);
    }
}
