<?php

declare(strict_types=1);

namespace App\Services\Lattice\Concerns;

use App\Models\Cart\CartItem;
use App\Models\Promotions\Promotion;
use App\Models\Promotions\PromotionRedemption;
use Lattice\Money as LatticeMoney;
use Lattice\Promotion\Budget as LatticeBudget;

trait BuildsLatticeBudget
{
    protected function makeBudget(Promotion $promotion): ?LatticeBudget
    {
        $redemptionBudget = $promotion->application_budget;
        $monetaryBudget = $promotion->getRawOriginal('monetary_budget');

        if (is_null($redemptionBudget) && is_null($monetaryBudget)) {
            return LatticeBudget::unlimited();
        }

        $consumedRedemptionBudget = 0;
        $consumedMonetaryBudget = 0;

        if ($promotion->exists && ! is_null($promotion->id)) {
            $baseRedemptionsQuery = PromotionRedemption::query()
                ->where('promotion_id', $promotion->id)
                ->where('redeemable_type', new CartItem()->getMorphClass());

            if (! is_null($redemptionBudget)) {
                $consumedRedemptionBudget = (clone $baseRedemptionsQuery)->count();
            }

            if (! is_null($monetaryBudget)) {
                $consumedMonetaryBudget =
                    (int) ((clone $baseRedemptionsQuery)
                        ->selectRaw(
                            'COALESCE(SUM(original_price - final_price), 0) AS consumed_budget',
                        )
                        ->value('consumed_budget') ?? 0);
            }
        }

        $remainingRedemptionBudget = is_null($redemptionBudget)
            ? null
            : max(0, (int) $redemptionBudget - $consumedRedemptionBudget);

        $remainingMonetaryBudget = is_null($monetaryBudget)
            ? null
            : max(0, (int) $monetaryBudget - $consumedMonetaryBudget);

        if (
            $remainingRedemptionBudget === 0 ||
            $remainingMonetaryBudget === 0
        ) {
            return null;
        }

        if (
            ! is_null($remainingRedemptionBudget) &&
            is_null($remainingMonetaryBudget)
        ) {
            return LatticeBudget::withRedemptionLimit(
                $remainingRedemptionBudget,
            );
        }

        if (
            is_null($remainingRedemptionBudget) &&
            ! is_null($remainingMonetaryBudget)
        ) {
            return LatticeBudget::withMonetaryLimit(
                new LatticeMoney(
                    $remainingMonetaryBudget,
                    $this->defaultCurrency(),
                ),
            );
        }

        return LatticeBudget::withBothLimits(
            $remainingRedemptionBudget,
            new LatticeMoney(
                $remainingMonetaryBudget,
                $this->defaultCurrency(),
            ),
        );
    }

    protected function defaultCurrency(): string
    {
        return (string) config('money.defaultCurrency', 'GBP');
    }
}
