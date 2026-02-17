<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\PromotionLayer;
use App\Services\Lattice\Stacks\LatticeLayerOutputStrategy;
use Closure;
use Lattice\Layer as LatticeLayer;
use Lattice\LayerOutput as LatticeLayerOutput;

class FakeLatticeLayerOutputStrategy implements LatticeLayerOutputStrategy
{
    /** @var list<string> */
    public array $builtLayerReferences = [];

    /**
     * @param  Closure(PromotionLayer): bool  $supports
     * @param  Closure(PromotionLayer, array<int, LatticeLayer>, ?LatticeLayer): LatticeLayerOutput  $make
     */
    public function __construct(
        private readonly Closure $supports,
        private readonly Closure $make,
    ) {}

    public function supports(PromotionLayer $layer): bool
    {
        return ($this->supports)($layer);
    }

    public function make(
        PromotionLayer $layer,
        array $latticeLayerIndex,
        ?LatticeLayer $passThroughLayer = null,
    ): LatticeLayerOutput {
        $this->builtLayerReferences[] = (string) $layer->reference;

        return ($this->make)($layer, $latticeLayerIndex, $passThroughLayer);
    }
}
