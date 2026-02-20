<?php

declare(strict_types=1);

namespace App\Services\PromotionQualification;

use App\Models\Promotions\MixAndMatchPromotion;
use App\Models\Promotions\MixAndMatchSlot;
use App\Models\Promotions\Promotion;
use App\Models\Promotions\Qualification;
use Illuminate\Support\Collection;
use RuntimeException;

class MixAndMatchStrategy implements PromotionQualificationStrategy
{
    public function __construct(
        private readonly QualificationEvaluator $qualificationEvaluator,
    ) {}

    public function supports(Promotion $promotion): bool
    {
        return $promotion->promotionable instanceof MixAndMatchPromotion;
    }

    /**
     * @param  string[]  $productTagNames
     */
    public function qualifies(
        Promotion $promotion,
        array $productTagNames,
    ): bool {
        $promotionable = $promotion->promotionable;

        if (! $promotionable instanceof MixAndMatchPromotion) {
            return false;
        }

        /** @var Collection<int, Qualification> $qualificationIndex */
        $qualificationIndex = $promotion->qualifications->keyBy('id');

        foreach ($promotionable->slots as $slot) {
            $slotQualification = $this->resolveSlotQualification(
                $promotion,
                $slot,
            );

            if (
                $this->qualificationEvaluator->evaluateQualification(
                    $slotQualification,
                    $productTagNames,
                    $qualificationIndex,
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function resolveSlotQualification(
        Promotion $promotion,
        MixAndMatchSlot $slot,
    ): Qualification {
        if (
            $slot->relationLoaded('qualification') &&
            $slot->qualification instanceof Qualification
        ) {
            return $slot->qualification;
        }

        $qualification = $promotion->qualifications->first(
            fn (Qualification $candidate): bool => $candidate->context ===
                'primary' &&
                $candidate->qualifiable_type === $slot->getMorphClass() &&
                (int) $candidate->qualifiable_id === (int) $slot->getKey(),
        );

        if ($qualification instanceof Qualification) {
            return $qualification;
        }

        throw new RuntimeException(
            sprintf(
                'Mix and match slot [%d] is missing its primary qualification.',
                $slot->getKey(),
            ),
        );
    }
}
