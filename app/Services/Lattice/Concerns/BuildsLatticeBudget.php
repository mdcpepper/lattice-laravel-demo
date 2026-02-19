<?php

declare(strict_types=1);

namespace App\Services\Lattice\Concerns;

use App\Models\CartItem;
use App\Models\Promotion as PromotionModel;
use App\Models\PromotionRedemption;
use Lattice\Money;
use Lattice\Promotions\Budget;

trait BuildsLatticeBudget
{
    protected function makeBudget(PromotionModel $promotion): ?Budget
    {
        $redemptionBudget = $promotion->application_budget;
        $monetaryBudget = $promotion->getRawOriginal('monetary_budget');

        if (is_null($redemptionBudget) && is_null($monetaryBudget)) {
            return Budget::unlimited();
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
            : max(0, (int) $redemptionBudget - (int) $consumedRedemptionBudget);

        $remainingMonetaryBudget = is_null($monetaryBudget)
            ? null
            : max(0, (int) $monetaryBudget - (int) $consumedMonetaryBudget);

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
            return Budget::withRedemptionLimit($remainingRedemptionBudget);
        }

        if (
            is_null($remainingRedemptionBudget) &&
            ! is_null($remainingMonetaryBudget)
        ) {
            return Budget::withMonetaryLimit(
                new Money($remainingMonetaryBudget, $this->defaultCurrency()),
            );
        }

        return Budget::withBothLimits(
            $remainingRedemptionBudget,
            new Money($remainingMonetaryBudget, $this->defaultCurrency()),
        );
    }

    protected function defaultCurrency(): string
    {
        return (string) config('money.defaultCurrency', 'GBP');
    }
}
