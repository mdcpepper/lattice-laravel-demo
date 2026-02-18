<?php

declare(strict_types=1);

namespace App\Services\Lattice\Promotions;

use App\Models\Promotion as PromotionModel;
use Lattice\Promotions\Promotion as LatticePromotion;
use RuntimeException;

class LatticePromotionFactory
{
    /**
     * @param  array<int, LatticePromotionStrategy>  $latticePromotionStrategies
     */
    public function __construct(
        private readonly array $latticePromotionStrategies,
    ) {}

    public function make(PromotionModel $promotion): ?LatticePromotion
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
        PromotionModel $promotion,
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
