<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Lattice;

use App\Models\Promotion;
use App\Services\Lattice\Promotions\LatticePromotionFactory;
use Lattice\Discount\Percentage;
use Lattice\Discount\SimpleDiscount;
use Lattice\Promotions\Budget;
use Lattice\Promotions\DirectDiscountPromotion;
use Lattice\Promotions\Promotion as LatticePromotion;
use Lattice\Qualification;
use Lattice\Qualification\BoolOp;
use RuntimeException;
use Tests\Helpers\FakeLatticePromotionStrategy;

test(
    'delegates lattice promotion building to the first supporting strategy',
    function (): void {
        $promotion = new Promotion(['name' => 'VIP Promo']);

        $nonSupportingStrategy = new FakeLatticePromotionStrategy(
            supports: fn (Promotion $promotion): bool => false,
            make: fn (
                Promotion $promotion,
            ): LatticePromotion => fakeLatticePromotion($promotion),
        );

        $supportingStrategy = new FakeLatticePromotionStrategy(
            supports: fn (Promotion $promotion): bool => true,
            make: fn (
                Promotion $promotion,
            ): LatticePromotion => fakeLatticePromotion($promotion),
        );

        $factory = new LatticePromotionFactory([
            $nonSupportingStrategy,
            $supportingStrategy,
        ]);

        $result = $factory->make($promotion);

        expect($result)
            ->toBeInstanceOf(DirectDiscountPromotion::class)
            ->and($nonSupportingStrategy->builtPromotionNames)
            ->toBeEmpty()
            ->and($supportingStrategy->builtPromotionNames)
            ->toBe(['VIP Promo']);
    },
);

test('throws when no strategy supports the promotion', function (): void {
    $promotion = new Promotion;
    $promotion->promotionable_type = 'unsupported/type';
    $promotion->setRelation('promotionable', null);

    $factory = new LatticePromotionFactory([
        new FakeLatticePromotionStrategy(
            supports: fn (Promotion $promotion): bool => false,
            make: fn (
                Promotion $promotion,
            ): LatticePromotion => fakeLatticePromotion($promotion),
        ),
    ]);

    expect(fn (): mixed => $factory->make($promotion))->toThrow(
        RuntimeException::class,
        'Unsupported promotionable type [unsupported/type].',
    );
});

function fakeLatticePromotion(Promotion $promotion): LatticePromotion
{
    return new DirectDiscountPromotion(
        reference: $promotion,
        qualification: new Qualification(BoolOp::AndOp, []),
        discount: SimpleDiscount::percentageOff(Percentage::fromDecimal(0.1)),
        budget: Budget::unlimited(),
    );
}
