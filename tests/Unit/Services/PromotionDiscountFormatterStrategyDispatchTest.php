<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Promotions\Promotion;
use App\Services\PromotionDiscount\PromotionDiscountFormatter;
use Tests\Helpers\FakePromotionDiscountStrategy;

test(
    'delegates discount formatting to the first supporting strategy',
    function (): void {
        $promotion = new Promotion(['name' => 'VIP Promo']);

        $nonSupportingStrategy = new FakePromotionDiscountStrategy(
            supports: fn (Promotion $promotion): bool => false,
            format: fn (Promotion $promotion): ?string => 'nope',
        );

        $supportingStrategy = new FakePromotionDiscountStrategy(
            supports: fn (Promotion $promotion): bool => true,
            format: fn (Promotion $promotion): ?string => 'Percentage Off: 10%',
        );

        $formatter = new PromotionDiscountFormatter([
            $nonSupportingStrategy,
            $supportingStrategy,
        ]);

        expect($formatter->format($promotion))
            ->toBe('Percentage Off: 10%')
            ->and($nonSupportingStrategy->formattedPromotionNames)
            ->toBeEmpty()
            ->and($supportingStrategy->formattedPromotionNames)
            ->toBe(['VIP Promo']);
    },
);

test('returns null when no strategy supports the promotion', function (): void {
    $promotion = new Promotion(['name' => 'No Handler']);

    $formatter = new PromotionDiscountFormatter([
        new FakePromotionDiscountStrategy(
            supports: fn (Promotion $promotion): bool => false,
            format: fn (Promotion $promotion): ?string => 'unused',
        ),
    ]);

    expect($formatter->format($promotion))->toBeNull();
});
