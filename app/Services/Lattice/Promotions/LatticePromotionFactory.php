<?php

declare(strict_types=1);

namespace App\Services\Lattice\Promotions;

use App\Models\Promotions\Promotion;
use Lattice\Promotion\PromotionInterface as LatticePromotion;
use RuntimeException;

readonly class LatticePromotionFactory
{
    /**
     * @param  array<int, LatticePromotionStrategy>  $latticePromotionStrategies
     */
    public function __construct(
        private array $latticePromotionStrategies,
    ) {}

    public function make(Promotion $promotion): ?LatticePromotion
    {
        $strategy = collect($this->latticePromotionStrategies)->first(
            fn (LatticePromotionStrategy $strategy): bool => $strategy->supports(
                $promotion,
            ),
        );

        if (! $strategy instanceof LatticePromotionStrategy) {
            throw $this->unsupportedPromotionableType($promotion);
        }

        return $strategy->make($promotion);
    }

    private function unsupportedPromotionableType(
        Promotion $promotion,
    ): RuntimeException {
        $promotionable = $promotion->relationLoaded('promotionable')
            ? $promotion->getRelation('promotionable')
            : null;

        return new RuntimeException(
            sprintf(
                'Unsupported promotionable type [%s].',
                $promotion->promotionable_type ??
                    get_debug_type($promotionable),
            ),
        );
    }
}
