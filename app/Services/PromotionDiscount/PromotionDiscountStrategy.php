<?php

declare(strict_types=1);

namespace App\Services\PromotionDiscount;

use App\Models\Promotions\Promotion;

interface PromotionDiscountStrategy
{
    public function supports(Promotion $promotion): bool;

    public function format(Promotion $promotion): ?string;
}
