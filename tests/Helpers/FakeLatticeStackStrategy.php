<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\Promotions\PromotionStack;
use App\Services\Lattice\Stacks\LatticeStackStrategy;
use Closure;
use Lattice\Stack\Stack as LatticeStack;

class FakeLatticeStackStrategy implements LatticeStackStrategy
{
    /** @var list<string> */
    public array $builtStackNames = [];

    /**
     * @param  Closure(PromotionStack): bool  $supports
     * @param  Closure(PromotionStack): LatticeStack  $make
     */
    public function __construct(
        private readonly Closure $supports,
        private readonly Closure $make,
    ) {}

    public function supports(PromotionStack $stack): bool
    {
        return ($this->supports)($stack);
    }

    public function make(PromotionStack $stack): LatticeStack
    {
        $this->builtStackNames[] = (string) $stack->name;

        return ($this->make)($stack);
    }
}
