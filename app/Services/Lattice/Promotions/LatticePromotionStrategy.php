<?php

declare(strict_types=1);

namespace App\Services\Lattice\Promotions;

use App\Models\Promotion as PromotionModel;
use Lattice\Promotion\PromotionInterface as LatticePromotion;

interface LatticePromotionStrategy
{
    public function supports(PromotionModel $promotion): bool;

    public function make(PromotionModel $promotion): ?LatticePromotion;
}
