<?php

declare(strict_types=1);

namespace App\Services\PromotionDiscount;

use App\Models\Promotions\DirectDiscountPromotion;
use App\Models\Promotions\Promotion;

class DirectDiscountStrategy implements PromotionDiscountStrategy
{
    use SimpleDiscountStrategy;

    public function supports(Promotion $promotion): bool
    {
        return $promotion->promotionable instanceof DirectDiscountPromotion;
    }
}
