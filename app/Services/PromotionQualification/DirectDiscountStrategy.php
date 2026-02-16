<?php

declare(strict_types=1);

namespace App\Services\PromotionQualification;

use App\Models\DirectDiscountPromotion;
use App\Models\Promotion;
use App\Models\Qualification;
use Illuminate\Support\Collection;
use RuntimeException;

class DirectDiscountStrategy implements PromotionQualificationStrategy
{
    public function __construct(
        private readonly QualificationEvaluator $qualificationEvaluator,
    ) {}

    public function supports(Promotion $promotion): bool
    {
        return $promotion->promotionable instanceof DirectDiscountPromotion;
    }

    /**
     * @param  string[]  $productTagNames
     */
    public function qualifies(
        Promotion $promotion,
        array $productTagNames,
    ): bool {
        $promotionable = $promotion->promotionable;

        if (! $promotionable instanceof DirectDiscountPromotion) {
            return false;
        }

        /** @var Collection<int, Qualification> $qualificationIndex */
        $qualificationIndex = $promotion->qualifications->keyBy('id');

        $rootQualification = $this->resolveDirectDiscountQualification(
            $promotion,
            $promotionable,
        );

        return $this->qualificationEvaluator->evaluateQualification(
            $rootQualification,
            $productTagNames,
            $qualificationIndex,
        );
    }

    private function resolveDirectDiscountQualification(
        Promotion $promotion,
        DirectDiscountPromotion $directPromotion,
    ): Qualification {
        if (
            $directPromotion->relationLoaded('qualification') &&
            $directPromotion->qualification instanceof Qualification
        ) {
            return $directPromotion->qualification;
        }

        $qualification = $promotion->qualifications->first(
            fn (Qualification $candidate): bool => $candidate->context ===
                'primary' &&
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
