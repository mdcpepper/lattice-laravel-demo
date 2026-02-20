<?php

declare(strict_types=1);

namespace App\Services\Lattice\Promotions;

use App\Enums\TieredThresholdDiscountKind;
use App\Models\Promotions\Promotion;
use App\Models\Promotions\Qualification;
use App\Models\Promotions\TieredThresholdDiscount;
use App\Models\Promotions\TieredThresholdPromotion;
use App\Models\Promotions\TieredThresholdTier;
use App\Services\Lattice\Concerns\BuildsLatticeBudget;
use App\Services\Lattice\Concerns\BuildsLatticeDiscountValues;
use App\Services\Lattice\Concerns\BuildsLatticeQualification;
use App\Services\Lattice\Concerns\HandlesUnsupportedPromotionableType;
use Lattice\Discount\Percentage;
use Lattice\Money;
use Lattice\Promotion\PromotionInterface as LatticePromotion;
use Lattice\Promotion\TieredThreshold\Discount as LatticeTieredThresholdDiscount;
use Lattice\Promotion\TieredThreshold\Threshold as LatticeTieredThresholdThreshold;
use Lattice\Promotion\TieredThreshold\Tier as LatticeTieredThresholdTier;
use Lattice\Promotion\TieredThreshold\TieredThreshold as LatticeTieredThresholdPromotion;
use RuntimeException;

class TieredThresholdPromotionStrategy implements LatticePromotionStrategy
{
    use BuildsLatticeBudget;
    use BuildsLatticeDiscountValues;
    use BuildsLatticeQualification;
    use HandlesUnsupportedPromotionableType;

    public function supports(Promotion $promotion): bool
    {
        return $promotion->promotionable instanceof TieredThresholdPromotion;
    }

    public function make(Promotion $promotion): ?LatticePromotion
    {
        $promotionable = $promotion->promotionable;

        if (! $promotionable instanceof TieredThresholdPromotion) {
            throw $this->unsupportedPromotionableType($promotion);
        }

        $budget = $this->makeBudget($promotion);

        if (is_null($budget)) {
            return null;
        }

        $qualificationIndex = $this->qualificationIndex($promotion);

        $tiers = $promotionable->tiers
            ->sortBy('sort_order')
            ->values()
            ->map(function (TieredThresholdTier $tier) use (
                $promotion,
                $qualificationIndex,
            ): LatticeTieredThresholdTier {
                $discount = $tier->discount;

                if (! $discount instanceof TieredThresholdDiscount) {
                    throw new RuntimeException(
                        sprintf(
                            'Tiered threshold tier [%d] is missing its discount relation.',
                            $tier->getKey(),
                        ),
                    );
                }

                $tierQualification = $this->resolveTierQualification(
                    $promotion,
                    $tier,
                );

                $latticeQualification = $this->makeQualification(
                    $tierQualification,
                    $qualificationIndex,
                );

                return new LatticeTieredThresholdTier(
                    lower_threshold: $this->makeLowerThreshold($tier),
                    upper_threshold: $this->makeUpperThreshold($tier),
                    contribution_qualification: $latticeQualification,
                    discount_qualification: $latticeQualification,
                    discount: $this->makeTieredThresholdDiscount($discount),
                );
            })
            ->all();

        return new LatticeTieredThresholdPromotion(
            reference: $promotion,
            tiers: $tiers,
            budget: $budget,
        );
    }

    private function resolveTierQualification(
        Promotion $promotion,
        TieredThresholdTier $tier,
    ): Qualification {
        $tierQualification = $tier->relationLoaded('qualification')
            ? $tier->qualification
            : null;

        if ($tierQualification instanceof Qualification) {
            return $tierQualification;
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

    private function makeLowerThreshold(
        TieredThresholdTier $tier,
    ): LatticeTieredThresholdThreshold {
        return $this->makeThreshold(
            $tier->lower_monetary_threshold_minor,
            $tier->lower_monetary_threshold_currency,
            $tier->lower_item_count_threshold,
            sprintf(
                'Tiered threshold tier [%d] is missing its lower threshold.',
                $tier->getKey(),
            ),
        );
    }

    private function makeUpperThreshold(
        TieredThresholdTier $tier,
    ): ?LatticeTieredThresholdThreshold {
        $upperMonetaryThreshold = $tier->upper_monetary_threshold_minor;
        $upperItemCountThreshold = $tier->upper_item_count_threshold;

        if (
            $upperMonetaryThreshold === null &&
            $upperItemCountThreshold === null
        ) {
            return null;
        }

        return $this->makeThreshold(
            $upperMonetaryThreshold,
            $tier->upper_monetary_threshold_currency,
            $upperItemCountThreshold,
            sprintf(
                'Tiered threshold tier [%d] has an invalid upper threshold.',
                $tier->getKey(),
            ),
        );
    }

    private function makeThreshold(
        ?int $monetaryThresholdMinor,
        ?string $monetaryThresholdCurrency,
        ?int $itemCountThreshold,
        string $missingThresholdMessage,
    ): LatticeTieredThresholdThreshold {
        if ($monetaryThresholdMinor === null && $itemCountThreshold === null) {
            throw new RuntimeException($missingThresholdMessage);
        }

        if ($monetaryThresholdMinor !== null && $itemCountThreshold !== null) {
            return LatticeTieredThresholdThreshold::withBothThresholds(
                monetary_threshold: new Money(
                    $monetaryThresholdMinor,
                    $monetaryThresholdCurrency ?? $this->defaultCurrency(),
                ),
                item_count_threshold: $itemCountThreshold,
            );
        }

        if ($monetaryThresholdMinor !== null) {
            return LatticeTieredThresholdThreshold::withMonetaryThreshold(
                monetary_threshold: new Money(
                    $monetaryThresholdMinor,
                    $monetaryThresholdCurrency ?? $this->defaultCurrency(),
                ),
            );
        }

        return LatticeTieredThresholdThreshold::withItemCountThreshold(
            item_count_threshold: (int) $itemCountThreshold,
        );
    }

    private function makeTieredThresholdDiscount(
        TieredThresholdDiscount $discount,
    ): LatticeTieredThresholdDiscount {
        $kind =
            $discount->kind instanceof TieredThresholdDiscountKind
                ? $discount->kind
                : TieredThresholdDiscountKind::from((string) $discount->kind);

        return match ($kind) {
            TieredThresholdDiscountKind::PercentageOffEachItem => LatticeTieredThresholdDiscount::percentageOffEachItem(
                Percentage::fromDecimal($this->normalizedPercentage($discount)),
            ),
            TieredThresholdDiscountKind::AmountOffEachItem => LatticeTieredThresholdDiscount::amountOffEachItem(
                $this->discountAmount($discount),
            ),
            TieredThresholdDiscountKind::OverrideEachItem => LatticeTieredThresholdDiscount::overrideEachItem(
                $this->discountAmount($discount),
            ),
            TieredThresholdDiscountKind::AmountOffTotal => LatticeTieredThresholdDiscount::amountOffTotal(
                $this->discountAmount($discount),
            ),
            TieredThresholdDiscountKind::OverrideTotal => LatticeTieredThresholdDiscount::overrideTotal(
                $this->discountAmount($discount),
            ),
            TieredThresholdDiscountKind::PercentageOffCheapest => LatticeTieredThresholdDiscount::percentageOffCheapest(
                Percentage::fromDecimal($this->normalizedPercentage($discount)),
            ),
            TieredThresholdDiscountKind::OverrideCheapest => LatticeTieredThresholdDiscount::overrideCheapest(
                $this->discountAmount($discount),
            ),
        };
    }
}
