<?php

declare(strict_types=1);

namespace App\Services\Lattice\Stacks;

use App\Models\PromotionLayer as PromotionLayerModel;
use Lattice\Layer as LatticeLayer;
use Lattice\LayerOutput as LatticeLayerOutput;

interface LatticeLayerOutputStrategy
{
    public function supports(PromotionLayerModel $layer): bool;

    /**
     * @param  array<int, LatticeLayer>  $latticeLayerIndex
     */
    public function make(
        PromotionLayerModel $layer,
        array $latticeLayerIndex,
        ?LatticeLayer $passThroughLayer = null,
    ): LatticeLayerOutput;
}
