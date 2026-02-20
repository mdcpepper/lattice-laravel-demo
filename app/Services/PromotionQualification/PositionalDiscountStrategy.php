<?php

namespace App\Services\PromotionQualification;

use App\Enums\QualificationContext;
use App\Models\Promotions\PositionalDiscountPromotion;
use App\Models\Promotions\Promotion;
use App\Models\Promotions\Qualification;
use RuntimeException;

readonly class PositionalDiscountStrategy implements PromotionQualificationStrategy
{
    public function __construct(
        private QualificationEvaluator $qualificationEvaluator,
    ) {}

    public function supports(Promotion $promotion): bool
    {
        return $promotion->promotionable instanceof PositionalDiscountPromotion;
    }

    /**
     * @param  string[]  $productTagNames
     */
    public function qualifies(
        Promotion $promotion,
        array $productTagNames,
    ): bool {
        $promotionable = $promotion->promotionable;

        if (! $promotionable instanceof PositionalDiscountPromotion) {
            return false;
        }

        /** @var Collection<int, Qualification> $qualificationIndex */
        $qualificationIndex = $promotion->qualifications->keyBy('id');

        $rootQualification = $this->resolveRootQualification(
            $promotion,
            $promotionable,
        );

        return $this->qualificationEvaluator->evaluateQualification(
            $rootQualification,
            $productTagNames,
            $qualificationIndex,
        );
    }

    private function resolveRootQualification(
        Promotion $promotion,
        PositionalDiscountPromotion $positionalPromotion,
    ): Qualification {
        if (
            $positionalPromotion->relationLoaded('qualification') &&
            $positionalPromotion->qualification instanceof Qualification
        ) {
            return $positionalPromotion->qualification;
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
