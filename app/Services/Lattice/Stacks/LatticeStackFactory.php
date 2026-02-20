<?php

declare(strict_types=1);

namespace App\Services\Lattice\Stacks;

use App\Models\Promotions\PromotionStack;
use Lattice\Stack\Stack as LatticeStack;
use RuntimeException;

class LatticeStackFactory
{
    /**
     * @param  array<int, LatticeStackStrategy>  $latticeStackStrategies
     */
    public function __construct(
        private readonly array $latticeStackStrategies,
    ) {}

    public function make(PromotionStack $stack): LatticeStack
    {
        $strategy = collect($this->latticeStackStrategies)->first(
            fn (LatticeStackStrategy $strategy): bool => $strategy->supports(
                $stack,
            ),
        );

        if (! $strategy instanceof LatticeStackStrategy) {
            throw $this->unsupportedStackType($stack);
        }

        return $strategy->make($stack);
    }

    private function unsupportedStackType(
        PromotionStack $stack,
    ): RuntimeException {
        return new RuntimeException(
            sprintf('Unsupported stack type [%s].', get_debug_type($stack)),
        );
    }
}
