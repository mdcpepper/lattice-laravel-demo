<?php

declare(strict_types=1);

namespace App\Services\Lattice;

use App\Models\Promotion as PromotionModel;
use Lattice\Promotions\Promotion as LatticePromotion;

interface LatticePromotionStrategy
{
    public function supports(PromotionModel $promotion): bool;

    public function make(PromotionModel $promotion): LatticePromotion;
}
