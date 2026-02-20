<?php

declare(strict_types=1);

namespace App\Services\Lattice\Promotions;

use App\Enums\QualificationContext;
use App\Models\Promotions\DirectDiscountPromotion;
use App\Models\Promotions\Promotion;
use App\Models\Promotions\Qualification;
use App\Models\Promotions\SimpleDiscount;
use App\Services\Lattice\Concerns\BuildsLatticeBudget;
use App\Services\Lattice\Concerns\BuildsLatticeDiscountValues;
use App\Services\Lattice\Concerns\BuildsLatticeQualification;
use App\Services\Lattice\Concerns\BuildsSimpleLatticeDiscount;
use App\Services\Lattice\Concerns\HandlesUnsupportedPromotionableType;
use Lattice\Promotion\Direct as LatticeDirect;
use Lattice\Promotion\PromotionInterface as LatticePromotion;
use RuntimeException;

class DirectDiscountPromotionStrategy implements LatticePromotionStrategy
{
    use BuildsLatticeBudget;
    use BuildsLatticeDiscountValues;
    use BuildsLatticeQualification;
    use BuildsSimpleLatticeDiscount;
    use HandlesUnsupportedPromotionableType;

    public function supports(Promotion $promotion): bool
    {
        return $promotion->promotionable instanceof DirectDiscountPromotion;
    }

    public function make(Promotion $promotion): ?LatticePromotion
    {
        $promotionable = $promotion->promotionable;

        if (! $promotionable instanceof DirectDiscountPromotion) {
            throw $this->unsupportedPromotionableType($promotion);
        }

        $discount = $promotionable->discount;

        if (! $discount instanceof SimpleDiscount) {
            throw new RuntimeException(
                'Direct discount promotion is missing its simple discount relation.',
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

        return new LatticeDirect(
            reference: $promotion,
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
        DirectDiscountPromotion $directPromotion,
    ): Qualification {
        $directQualification = $directPromotion->relationLoaded('qualification')
            ? $directPromotion->qualification
            : null;

        if ($directQualification instanceof Qualification) {
            return $directQualification;
        }

        $qualification = $promotion->qualifications->first(
            fn (Qualification $candidate): bool => $candidate->context ===
                QualificationContext::Primary->value &&
                $candidate->qualifiable_type ===
                    $directPromotion->getMorphClass() &&
                (int) $candidate->qualifiable_id ===
                    (int) $directPromotion->getKey(),
        );

        if ($qualification instanceof Qualification) {
            return $qualification;
        }

        throw new RuntimeException(
            'Direct discount promotion is missing its primary qualification.',
        );
    }
}
