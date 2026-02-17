<?php

declare(strict_types=1);

namespace App\Services\Lattice\Concerns;

use App\Models\Promotion as PromotionModel;
use Lattice\Money;
use Lattice\Promotions\Budget;

trait BuildsLatticeBudget
{
    protected function makeBudget(PromotionModel $promotion): Budget
    {
        $applicationBudget = $promotion->application_budget;
        $monetaryBudget = $promotion->getRawOriginal('monetary_budget');

        if (is_null($applicationBudget) && is_null($monetaryBudget)) {
            return Budget::unlimited();
        }

        if (! is_null($applicationBudget) && is_null($monetaryBudget)) {
            return Budget::withApplicationLimit((int) $applicationBudget);
        }

        if (is_null($applicationBudget) && ! is_null($monetaryBudget)) {
            return Budget::withMonetaryLimit(
                new Money((int) $monetaryBudget, $this->defaultCurrency()),
            );
        }

        return Budget::withBothLimits(
            (int) $applicationBudget,
            new Money((int) $monetaryBudget, $this->defaultCurrency()),
        );
    }

    protected function defaultCurrency(): string
    {
        return (string) config('money.defaultCurrency', 'GBP');
    }
}
