<?php

declare(strict_types=1);

namespace App\Services\Lattice\Promotions;

use App\Models\Promotions\Promotion;
use Lattice\Promotion\PromotionInterface as LatticePromotion;

interface LatticePromotionStrategy
{
    public function supports(Promotion $promotion): bool;

    public function make(Promotion $promotion): ?LatticePromotion;
}
