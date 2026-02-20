<?php

declare(strict_types=1);

namespace App\Services\Lattice\Promotions;

use App\Enums\QualificationContext;
use App\Models\Promotions\PositionalDiscountPosition;
use App\Models\Promotions\PositionalDiscountPromotion;
use App\Models\Promotions\Promotion;
use App\Models\Promotions\Qualification;
use App\Models\Promotions\SimpleDiscount;
use App\Services\Lattice\Concerns\BuildsLatticeBudget;
use App\Services\Lattice\Concerns\BuildsLatticeDiscountValues;
use App\Services\Lattice\Concerns\BuildsLatticeQualification;
use App\Services\Lattice\Concerns\BuildsSimpleLatticeDiscount;
use App\Services\Lattice\Concerns\HandlesUnsupportedPromotionableType;
use Lattice\Promotion\Positional as LatticePositional;
use Lattice\Promotion\PromotionInterface as LatticePromotion;
use RuntimeException;

class PositionalDiscountPromotionStrategy implements LatticePromotionStrategy
{
    use BuildsLatticeBudget;
    use BuildsLatticeDiscountValues;
    use BuildsLatticeQualification;
    use BuildsSimpleLatticeDiscount;
    use HandlesUnsupportedPromotionableType;

    public function supports(Promotion $promotion): bool
    {
        return $promotion->promotionable instanceof PositionalDiscountPromotion;
    }

    public function make(Promotion $promotion): ?LatticePromotion
    {
        $promotionable = $promotion->promotionable;

        if (! $promotionable instanceof PositionalDiscountPromotion) {
            throw $this->unsupportedPromotionableType($promotion);
        }

        $discount = $promotionable->discount;

        if (! $discount instanceof SimpleDiscount) {
            throw new RuntimeException(
                'Positional discount promotion is missing its simple discount relation.',
            );
        }

        $budget = $this->makeBudget($promotion);

        if (is_null($budget)) {
            return null;
        }

        $qualificationIndex = $this->qualificationIndex($promotion);

        $rootQualification = $this->resolveRootQualification(
            $promotion,
            $promotionable,
        );

        $positions = $promotionable->positions
            ->sortBy('sort_order')
            ->values()
            ->map(
                fn (
                    PositionalDiscountPosition $position,
                ): int => $position->position,
            )
            ->all();

        return new LatticePositional(
            reference: $promotion,
            size: $promotionable->size,
            positions: $positions,
            qualification: $this->makeQualification(
                $rootQualification,
                $qualificationIndex,
            ),
            discount: $this->makeSimpleDiscount($discount),
            budget: $budget,
        );
    }

    private function resolveRootQualification(
        Promotion $promotion,
        PositionalDiscountPromotion $positionalPromotion,
    ): Qualification {
        $positionalQualification = $positionalPromotion->relationLoaded(
            'qualification',
        )
            ? $positionalPromotion->qualification
            : null;

        if ($positionalQualification instanceof Qualification) {
            return $positionalQualification;
        }

        $qualification = $promotion->qualifications->first(
            fn (Qualification $candidate): bool => $candidate->context ===
                QualificationContext::Primary->value &&
                $candidate->qualifiable_type ===
                    $positionalPromotion->getMorphClass() &&
                (int) $candidate->qualifiable_id ===
                    (int) $positionalPromotion->getKey(),
        );

        if ($qualification instanceof Qualification) {
            return $qualification;
        }

        throw new RuntimeException(
            'Positional discount promotion is missing its primary qualification.',
        );
    }
}
