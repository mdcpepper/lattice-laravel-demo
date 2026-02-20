<?php

declare(strict_types=1);

namespace App\Services\Lattice\Promotions;

use App\Enums\MixAndMatchDiscountKind;
use App\Models\Promotions\MixAndMatchDiscount;
use App\Models\Promotions\MixAndMatchPromotion;
use App\Models\Promotions\MixAndMatchSlot;
use App\Models\Promotions\Promotion;
use App\Models\Promotions\Qualification;
use App\Services\Lattice\Concerns\BuildsLatticeBudget;
use App\Services\Lattice\Concerns\BuildsLatticeDiscountValues;
use App\Services\Lattice\Concerns\BuildsLatticeQualification;
use App\Services\Lattice\Concerns\HandlesUnsupportedPromotionableType;
use Lattice\Discount\Percentage;
use Lattice\Promotion\MixAndMatch\Discount as LatticeMixAndMatchDiscount;
use Lattice\Promotion\MixAndMatch\MixAndMatch as LatticeMixAndMatchPromotion;
use Lattice\Promotion\MixAndMatch\Slot as LatticeMixAndMatchSlot;
use Lattice\Promotion\PromotionInterface as LatticePromotion;
use RuntimeException;

class MixAndMatchPromotionStrategy implements LatticePromotionStrategy
{
    use BuildsLatticeBudget;
    use BuildsLatticeDiscountValues;
    use BuildsLatticeQualification;
    use HandlesUnsupportedPromotionableType;

    public function supports(Promotion $promotion): bool
    {
        return $promotion->promotionable instanceof MixAndMatchPromotion;
    }

    public function make(Promotion $promotion): ?LatticePromotion
    {
        $promotionable = $promotion->promotionable;

        if (! $promotionable instanceof MixAndMatchPromotion) {
            throw $this->unsupportedPromotionableType($promotion);
        }

        $discount = $promotionable->discount;

        if (! $discount instanceof MixAndMatchDiscount) {
            throw new RuntimeException(
                'Mix and match promotion is missing its discount relation.',
            );
        }

        $budget = $this->makeBudget($promotion);

        if (is_null($budget)) {
            return null;
        }

        $qualificationIndex = $this->qualificationIndex($promotion);

        $slots = $promotionable->slots
            ->sortBy('sort_order')
            ->values()
            ->map(
                fn (
                    MixAndMatchSlot $slot,
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
            budget: $budget,
        );
    }

    private function resolveSlotQualification(
        Promotion $promotion,
        MixAndMatchSlot $slot,
    ): Qualification {
        $slotQualification = $slot->relationLoaded('qualification')
            ? $slot->qualification
            : null;

        if ($slotQualification instanceof Qualification) {
            return $slotQualification;
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

    private function makeMixAndMatchDiscount(
        MixAndMatchDiscount $discount,
    ): LatticeMixAndMatchDiscount {
        $kind =
            $discount->kind instanceof MixAndMatchDiscountKind
                ? $discount->kind
                : MixAndMatchDiscountKind::from((string) $discount->kind);

        return match ($kind) {
            MixAndMatchDiscountKind::PercentageOffAllItems => LatticeMixAndMatchDiscount::percentageOffAllItems(
                Percentage::fromDecimal($this->normalizedPercentage($discount)),
            ),
            MixAndMatchDiscountKind::AmountOffEachItem => LatticeMixAndMatchDiscount::amountOffEachItem(
                $this->discountAmount($discount),
            ),
            MixAndMatchDiscountKind::OverrideEachItem => LatticeMixAndMatchDiscount::overrideEachItem(
                $this->discountAmount($discount),
            ),
            MixAndMatchDiscountKind::AmountOffTotal => LatticeMixAndMatchDiscount::amountOffTotal(
                $this->discountAmount($discount),
            ),
            MixAndMatchDiscountKind::OverrideTotal => LatticeMixAndMatchDiscount::overrideTotal(
                $this->discountAmount($discount),
            ),
            MixAndMatchDiscountKind::PercentageOffCheapest => LatticeMixAndMatchDiscount::percentageOffCheapest(
                Percentage::fromDecimal($this->normalizedPercentage($discount)),
            ),
            MixAndMatchDiscountKind::OverrideCheapest => LatticeMixAndMatchDiscount::overrideCheapest(
                $this->discountAmount($discount),
            ),
        };
    }
}
