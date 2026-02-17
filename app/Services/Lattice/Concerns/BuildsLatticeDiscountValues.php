<?php

declare(strict_types=1);

namespace App\Services\Lattice\Concerns;

use App\Contracts\Discount as DiscountContract;
use Lattice\Money;
use RuntimeException;

trait BuildsLatticeDiscountValues
{
    protected function normalizedPercentage(DiscountContract $discount): float
    {
        $percentage = $discount->discountPercentage();

        if (is_null($percentage)) {
            throw new RuntimeException(
                'Percentage discount is missing percentage value.',
            );
        }

        return ((float) $percentage) / 100;
    }

    protected function discountAmount(DiscountContract $discount): Money
    {
        $amount = $discount->discountAmount();
        $currency = $discount->discountAmountCurrency();

        if (is_null($amount) || is_null($currency)) {
            throw new RuntimeException(
                'Amount discount is missing amount and/or amount_currency.',
            );
        }

        return new Money((int) $amount, (string) $currency);
    }
}
