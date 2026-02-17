<?php

declare(strict_types=1);

namespace App\Services\PromotionQualification;

use App\Models\Promotion;
use App\Models\Qualification;
use App\Models\TieredThresholdPromotion;
use App\Models\TieredThresholdTier;
use Illuminate\Support\Collection;
use RuntimeException;

class TieredThresholdStrategy implements PromotionQualificationStrategy
{
    public function __construct(
        private readonly QualificationEvaluator $qualificationEvaluator,
    ) {}

    public function supports(Promotion $promotion): bool
    {
        return $promotion->promotionable instanceof TieredThresholdPromotion;
    }

    /**
     * @param  string[]  $productTagNames
     */
    public function qualifies(
        Promotion $promotion,
        array $productTagNames,
    ): bool {
        $promotionable = $promotion->promotionable;

        if (! $promotionable instanceof TieredThresholdPromotion) {
            return false;
        }

        /** @var Collection<int, Qualification> $qualificationIndex */
        $qualificationIndex = $promotion->qualifications->keyBy('id');

        foreach ($promotionable->tiers as $tier) {
            $tierQualification = $this->resolveTierQualification(
                $promotion,
                $tier,
            );

            if (
                $this->qualificationEvaluator->evaluateQualification(
                    $tierQualification,
                    $productTagNames,
                    $qualificationIndex,
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function resolveTierQualification(
        Promotion $promotion,
        TieredThresholdTier $tier,
    ): Qualification {
        if (
            $tier->relationLoaded('qualification') &&
            $tier->qualification instanceof Qualification
        ) {
            return $tier->qualification;
        }

        $qualification = $promotion->qualifications->first(
            fn (Qualification $candidate): bool => $candidate->context ===
                'primary' &&
                $candidate->qualifiable_type === $tier->getMorphClass() &&
                (int) $candidate->qualifiable_id === (int) $tier->getKey(),
        );

        if ($qualification instanceof Qualification) {
            return $qualification;
        }

        throw new RuntimeException(
            sprintf(
                'Tiered threshold tier [%d] is missing its primary qualification.',
                $tier->getKey(),
            ),
        );
    }
}
