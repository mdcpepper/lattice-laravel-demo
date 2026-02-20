<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\Promotions\PromotionLayer;
use App\Services\Lattice\Stacks\LatticeLayerStrategy;
use Closure;
use Lattice\Stack\Layer as LatticeLayer;

class FakeLatticeLayerStrategy implements LatticeLayerStrategy
{
    /** @var list<string> */
    public array $builtLayerReferences = [];

    /**
     * @param  Closure(PromotionLayer): bool  $supports
     * @param  Closure(PromotionLayer): LatticeLayer  $make
     */
    public function __construct(
        private readonly Closure $supports,
        private readonly Closure $make,
    ) {}

    public function supports(PromotionLayer $layer): bool
    {
        return ($this->supports)($layer);
    }

    public function make(PromotionLayer $layer): LatticeLayer
    {
        $this->builtLayerReferences[] = (string) $layer->reference;

        return ($this->make)($layer);
    }
}
