<?php

declare(strict_types=1);

namespace App\Services\Lattice\Promotions;

use App\Enums\QualificationContext;
use App\Models\PositionalDiscountPosition as PositionalDiscountPositionModel;
use App\Models\PositionalDiscountPromotion as PositionalDiscountPromotionModel;
use App\Models\Promotion as PromotionModel;
use App\Models\Qualification as QualificationModel;
use App\Models\SimpleDiscount as SimpleDiscountModel;
use App\Services\Lattice\Concerns\BuildsLatticeBudget;
use App\Services\Lattice\Concerns\BuildsLatticeDiscountValues;
use App\Services\Lattice\Concerns\BuildsLatticeQualification;
use App\Services\Lattice\Concerns\BuildsSimpleLatticeDiscount;
use App\Services\Lattice\Concerns\HandlesUnsupportedPromotionableType;
use Lattice\Promotions\PositionalDiscountPromotion;
use Lattice\Promotions\Promotion as LatticePromotion;
use RuntimeException;

class PositionalDiscountPromotionStrategy implements LatticePromotionStrategy
{
    use BuildsLatticeBudget;
    use BuildsLatticeDiscountValues;
    use BuildsLatticeQualification;
    use BuildsSimpleLatticeDiscount;
    use HandlesUnsupportedPromotionableType;

    public function supports(PromotionModel $promotion): bool
    {
        return $promotion->promotionable instanceof PositionalDiscountPromotionModel;
    }

    public function make(PromotionModel $promotion): LatticePromotion
    {
        $promotionable = $promotion->promotionable;

        if (! $promotionable instanceof PositionalDiscountPromotionModel) {
            throw $this->unsupportedPromotionableType($promotion);
        }

        $discount = $promotionable->discount;

        if (! $discount instanceof SimpleDiscountModel) {
            throw new RuntimeException(
                'Positional discount promotion is missing its simple discount relation.',
            );
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
                    PositionalDiscountPositionModel $position,
                ): int => $position->position,
            )
            ->all();

        return new PositionalDiscountPromotion(
            reference: $promotion,
            qualification: $this->makeQualification(
                $rootQualification,
                $qualificationIndex,
            ),
            discount: $this->makeSimpleDiscount($discount),
            budget: $this->makeBudget($promotion),
            size: $promotionable->size,
            positions: $positions,
        );
    }

    private function resolveRootQualification(
        PromotionModel $promotion,
        PositionalDiscountPromotionModel $positionalPromotion,
    ): QualificationModel {
        $positionalQualification = $positionalPromotion->relationLoaded(
            'qualification',
        )
            ? $positionalPromotion->qualification
            : null;

        if ($positionalQualification instanceof QualificationModel) {
            return $positionalQualification;
        }

        $qualification = $promotion->qualifications->first(
            fn (QualificationModel $candidate): bool => $candidate->context ===
                QualificationContext::Primary->value &&
                $candidate->qualifiable_type ===
                    $positionalPromotion->getMorphClass() &&
                (int) $candidate->qualifiable_id ===
                    (int) $positionalPromotion->getKey(),
        );

        if ($qualification instanceof QualificationModel) {
            return $qualification;
        }

        throw new RuntimeException(
            'Positional discount promotion is missing its primary qualification.',
        );
    }
}
