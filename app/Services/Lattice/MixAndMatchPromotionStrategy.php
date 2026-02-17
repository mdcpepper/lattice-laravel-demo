<?php

declare(strict_types=1);

namespace App\Services\Lattice;

use App\Models\MixAndMatchDiscount as MixAndMatchDiscountModel;
use App\Models\MixAndMatchPromotion as MixAndMatchPromotionModel;
use App\Models\MixAndMatchSlot as MixAndMatchSlotModel;
use App\Models\Promotion as PromotionModel;
use App\Models\Qualification as QualificationModel;
use Lattice\Promotions\MixAndMatch\Slot as LatticeMixAndMatchSlot;
use Lattice\Promotions\MixAndMatchPromotion as LatticeMixAndMatchPromotion;
use Lattice\Promotions\Promotion as LatticePromotion;
use RuntimeException;

class MixAndMatchPromotionStrategy extends BaseLatticePromotionStrategy
{
    public function supports(PromotionModel $promotion): bool
    {
        return $promotion->promotionable instanceof MixAndMatchPromotionModel;
    }

    public function make(PromotionModel $promotion): LatticePromotion
    {
        $promotionable = $promotion->promotionable;

        if (! $promotionable instanceof MixAndMatchPromotionModel) {
            throw $this->unsupportedPromotionableType($promotion);
        }

        $discount = $promotionable->discount;

        if (! $discount instanceof MixAndMatchDiscountModel) {
            throw new RuntimeException(
                'Mix and match promotion is missing its discount relation.',
            );
        }

        $qualificationIndex = $this->qualificationIndex($promotion);

        $slots = $promotionable->slots
            ->sortBy('sort_order')
            ->values()
            ->map(
                fn (
                    MixAndMatchSlotModel $slot,
                ): LatticeMixAndMatchSlot => new LatticeMixAndMatchSlot(
                    reference: $slot,
                    qualification: $this->makeQualification(
                        $this->resolveSlotQualification($promotion, $slot),
                        $qualificationIndex,
                    ),
                    min: (int) $slot->min,
                    max: is_null($slot->max) ? null : (int) $slot->max,
                ),
            )
            ->all();

        return new LatticeMixAndMatchPromotion(
            reference: $promotion,
            slots: $slots,
            discount: $this->makeMixAndMatchDiscount($discount),
            budget: $this->makeBudget($promotion),
        );
    }

    private function resolveSlotQualification(
        PromotionModel $promotion,
        MixAndMatchSlotModel $slot,
    ): QualificationModel {
        $slotQualification = $slot->relationLoaded('qualification')
            ? $slot->qualification
            : null;

        if ($slotQualification instanceof QualificationModel) {
            return $slotQualification;
        }

        $qualification = $promotion->qualifications->first(
            fn (QualificationModel $candidate): bool => $candidate->context ===
                'primary' &&
                $candidate->qualifiable_type === $slot->getMorphClass() &&
                (int) $candidate->qualifiable_id === (int) $slot->getKey(),
        );

        if ($qualification instanceof QualificationModel) {
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
