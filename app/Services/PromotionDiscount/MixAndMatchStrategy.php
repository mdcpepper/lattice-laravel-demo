<?php

declare(strict_types=1);

namespace App\Services\PromotionDiscount;

use App\Enums\MixAndMatchDiscountKind;
use App\Models\MixAndMatchDiscount;
use App\Models\MixAndMatchPromotion;
use App\Models\Promotion;

class MixAndMatchStrategy implements PromotionDiscountStrategy
{
    public function supports(Promotion $promotion): bool
    {
        return $promotion->promotionable instanceof MixAndMatchPromotion;
    }

    public function format(Promotion $promotion): ?string
    {
        $promotionable = $promotion->promotionable;

        if (! $promotionable instanceof MixAndMatchPromotion) {
            return null;
        }

        $discount = $promotionable->discount;

        if (! $discount instanceof MixAndMatchDiscount) {
            return null;
        }

        $kind =
            $discount->kind instanceof MixAndMatchDiscountKind
                ? $discount->kind
                : MixAndMatchDiscountKind::from((string) $discount->kind);

        $value = in_array(
            $kind->value,
            MixAndMatchDiscountKind::percentageTypes(),
            true,
        )
            ? $this->formatPercentage($discount->discountPercentage())
            : $this->formatAmount(
                $discount->discountAmount(),
                $discount->discountAmountCurrency(),
            );

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
