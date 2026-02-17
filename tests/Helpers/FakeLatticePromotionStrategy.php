<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\Promotion;
use App\Services\Lattice\LatticePromotionStrategy;
use Closure;
use Lattice\Promotions\Promotion as LatticePromotion;

class FakeLatticePromotionStrategy implements LatticePromotionStrategy
{
    /** @var list<string> */
    public array $builtPromotionNames = [];

    /**
     * @param  Closure(Promotion): bool  $supports
     * @param  Closure(Promotion): LatticePromotion  $make
     */
    public function __construct(
        private readonly Closure $supports,
        private readonly Closure $make,
    ) {}

    public function supports(Promotion $promotion): bool
    {
        return ($this->supports)($promotion);
    }

    public function make(Promotion $promotion): LatticePromotion
    {
        $this->builtPromotionNames[] = (string) $promotion->name;

        return ($this->make)($promotion);
    }
}
