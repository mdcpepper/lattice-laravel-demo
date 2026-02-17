<?php

declare(strict_types=1);

namespace App\Services\PromotionDiscount;

use App\Enums\SimpleDiscountKind;
use App\Models\Promotion;
use App\Models\SimpleDiscount;

trait SimpleDiscountStrategy
{
    abstract public function supports(Promotion $promotion): bool;

    public function format(Promotion $promotion): ?string
    {
        if (! $this->supports($promotion)) {
            return null;
        }

        $promotionable = $promotion->promotionable;

        $discount = $promotionable->discount;

        if (! $discount instanceof SimpleDiscount) {
            return null;
        }

        $kind =
            $discount->kind instanceof SimpleDiscountKind
                ? $discount->kind
                : SimpleDiscountKind::from((string) $discount->kind);

        $value = match ($kind) {
            SimpleDiscountKind::PercentageOff => $this->formatPercentage(
                $discount->discountPercentage(),
            ),
            SimpleDiscountKind::AmountOff,
            SimpleDiscountKind::AmountOverride => $this->formatAmount(
                $discount->discountAmount(),
                $discount->discountAmountCurrency(),
            ),
        };

        if ($value === null) {
            return $kind->name();
        }

        return sprintf('%s: %s', $kind->name(), $value);
    }

    private function formatPercentage(?float $percentage): ?string
    {
        if ($percentage === null) {
            return null;
        }

        return sprintf(
            '%s%%',
            rtrim(rtrim(number_format($percentage, 2, '.', ''), '0'), '.'),
        );
    }

    private function formatAmount(?int $amount, ?string $currency): ?string
    {
        if ($amount === null) {
            return null;
        }

        $majorUnitAmount = number_format($amount / 100, 2, '.', '');

        if ($currency === null || strtoupper($currency) === 'GBP') {
            return sprintf('£%s', $majorUnitAmount);
        }

        return sprintf('%s %s', strtoupper($currency), $majorUnitAmount);
    }
}
