<?php

declare(strict_types=1);

namespace App\Services\Lattice\Concerns;

use App\Contracts\Discount as DiscountContract;
use App\Enums\SimpleDiscountKind;
use App\Models\SimpleDiscount as SimpleDiscountModel;
use Lattice\Discount\Percentage;
use Lattice\Discount\SimpleDiscount;
use Lattice\Money;

trait BuildsSimpleLatticeDiscount
{
    abstract protected function normalizedPercentage(
        DiscountContract $discount,
    ): float;

    abstract protected function discountAmount(
        DiscountContract $discount,
    ): Money;

    protected function makeSimpleDiscount(
        SimpleDiscountModel $discount,
    ): SimpleDiscount {
        $kind =
            $discount->kind instanceof SimpleDiscountKind
                ? $discount->kind
                : SimpleDiscountKind::from((string) $discount->kind);

        return match ($kind) {
            SimpleDiscountKind::PercentageOff => SimpleDiscount::percentageOff(
                Percentage::fromDecimal($this->normalizedPercentage($discount)),
            ),
            SimpleDiscountKind::AmountOverride => SimpleDiscount::amountOverride(
                $this->discountAmount($discount),
            ),
            SimpleDiscountKind::AmountOff => SimpleDiscount::amountOff(
                $this->discountAmount($discount),
            ),
        };
    }
}
