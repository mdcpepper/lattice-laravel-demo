<?php

declare(strict_types=1);

namespace App\Services\PromotionDiscount;

use App\Models\Promotions\PositionalDiscountPromotion;
use App\Models\Promotions\Promotion;

class PositionalDiscountStrategy implements PromotionDiscountStrategy
{
    use SimpleDiscountStrategy;

    public function supports(Promotion $promotion): bool
    {
        return $promotion->promotionable instanceof PositionalDiscountPromotion;
    }
}
