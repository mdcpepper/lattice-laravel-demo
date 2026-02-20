<?php

declare(strict_types=1);

namespace App\Services\Lattice\Concerns;

use App\Contracts\Discount as DiscountContract;
use App\Enums\SimpleDiscountKind;
use App\Models\SimpleDiscount as SimpleDiscountModel;
use Lattice\Discount\Percentage as LatticePercentage;
use Lattice\Discount\Simple as LatticeSimple;
use Lattice\Money as LatticeMoney;

trait BuildsSimpleLatticeDiscount
{
    abstract protected function normalizedPercentage(
        DiscountContract $discount,
    ): float;

    abstract protected function discountAmount(
        DiscountContract $discount,
    ): LatticeMoney;

    protected function makeSimpleDiscount(
        SimpleDiscountModel $discount,
    ): LatticeSimple {
        $kind =
            $discount->kind instanceof SimpleDiscountKind
                ? $discount->kind
                : SimpleDiscountKind::from((string) $discount->kind);

        return match ($kind) {
            SimpleDiscountKind::PercentageOff => LatticeSimple::percentageOff(
                LatticePercentage::fromDecimal(
                    $this->normalizedPercentage($discount),
                ),
            ),
            SimpleDiscountKind::AmountOverride => LatticeSimple::amountOverride(
                $this->discountAmount($discount),
            ),
            SimpleDiscountKind::AmountOff => LatticeSimple::amountOff(
                $this->discountAmount($discount),
            ),
        };
    }
}
