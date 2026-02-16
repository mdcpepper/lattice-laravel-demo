<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Promotion;
use App\Services\PromotionDiscount\PromotionDiscountFormatter;
use App\Services\PromotionDiscount\PromotionDiscountStrategy;
use Closure;

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

class FakePromotionDiscountStrategy implements PromotionDiscountStrategy
{
    /** @var list<string> */
    public array $formattedPromotionNames = [];

    /**
     * @param  Closure(Promotion): bool  $supports
     * @param  Closure(Promotion): ?string  $format
     */
    public function __construct(
        private readonly Closure $supports,
        private readonly Closure $format,
    ) {}

    public function supports(Promotion $promotion): bool
    {
        return ($this->supports)($promotion);
    }

    public function format(Promotion $promotion): ?string
    {
        $this->formattedPromotionNames[] = (string) $promotion->name;

        return ($this->format)($promotion);
    }
}
