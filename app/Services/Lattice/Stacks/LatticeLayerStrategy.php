<?php

declare(strict_types=1);

namespace App\Services\Lattice\Stacks;

use App\Models\Promotions\PromotionLayer as PromotionLayerModel;
use Lattice\Stack\Layer as LatticeLayer;

interface LatticeLayerStrategy
{
    public function supports(PromotionLayerModel $layer): bool;

    public function make(PromotionLayerModel $layer): LatticeLayer;
}
