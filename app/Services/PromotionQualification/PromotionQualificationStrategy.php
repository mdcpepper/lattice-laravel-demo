<?php

declare(strict_types=1);

namespace App\Services\PromotionQualification;

use App\Models\Promotions\Promotion;

interface PromotionQualificationStrategy
{
    public function supports(Promotion $promotion): bool;

    /**
     * @param  string[]  $productTagNames
     */
    public function qualifies(
        Promotion $promotion,
        array $productTagNames,
    ): bool;
}
