<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\Promotion;
use App\Services\ProductQualificationChecker;
use App\Services\PromotionQualification\PromotionQualificationStrategy;
use Closure;
use Illuminate\Support\Collection;

test(
    'delegates promotion qualification to the first supporting strategy',
    function (): void {
        $product = new Product;
        $product->setRelation('tags', collect([(object) ['name' => 'VIP']]));

        $promotion = new Promotion(['name' => 'VIP Promo']);
        $promotion->setRelation('qualifications', collect());

        $nonSupportingStrategy = new FakePromotionQualificationStrategy(
            supports: fn (Promotion $promotion): bool => false,
            qualifies: fn (
                Promotion $promotion,
                array $productTagNames,
            ): bool => false,
        );

        $supportingStrategy = new FakePromotionQualificationStrategy(
            supports: fn (Promotion $promotion): bool => true,
            qualifies: fn (
                Promotion $promotion,
                array $productTagNames,
            ): bool => $productTagNames === ['vip'],
        );

        $checker = new ProductQualificationChecker([
            $nonSupportingStrategy,
            $supportingStrategy,
        ]);

        seedCheckerPromotions($checker, collect([$promotion]));

        expect($checker->qualifyingPromotionNames($product))
            ->toBe(['VIP Promo'])
            ->and($nonSupportingStrategy->qualifiedPromotionNames)
            ->toBeEmpty()
            ->and($supportingStrategy->qualifiedPromotionNames)
            ->toBe(['VIP Promo']);
    },
);

test(
    'returns an empty list when no strategy supports the promotion',
    function (): void {
        $product = new Product;
        $product->setRelation(
            'tags',
            collect([(object) ['name' => 'eligible']]),
        );

        $promotion = new Promotion(['name' => 'No Handler']);
        $promotion->setRelation('qualifications', collect());

        $checker = new ProductQualificationChecker([
            new FakePromotionQualificationStrategy(
                supports: fn (Promotion $promotion): bool => false,
                qualifies: fn (
                    Promotion $promotion,
                    array $productTagNames,
                ): bool => true,
            ),
        ]);

        seedCheckerPromotions($checker, collect([$promotion]));

        expect($checker->qualifyingPromotionNames($product))->toBeEmpty();
    },
);

/**
 * @param  Collection<int, Promotion>  $promotions
 */
function seedCheckerPromotions(
    ProductQualificationChecker $checker,
    Collection $promotions,
): void {
    (function (Collection $promotions): void {
        $this->promotions = $promotions;
    })->call($checker, $promotions);
}

class FakePromotionQualificationStrategy implements PromotionQualificationStrategy
{
    /** @var list<string> */
    public array $qualifiedPromotionNames = [];

    /**
     * @param  Closure(Promotion): bool  $supports
     * @param  Closure(Promotion, array<int, string>): bool  $qualifies
     */
    public function __construct(
        private readonly Closure $supports,
        private readonly Closure $qualifies,
    ) {}

    public function supports(Promotion $promotion): bool
    {
        return ($this->supports)($promotion);
    }

    /**
     * @param  string[]  $productTagNames
     */
    public function qualifies(
        Promotion $promotion,
        array $productTagNames,
    ): bool {
        $this->qualifiedPromotionNames[] = (string) $promotion->name;

        return ($this->qualifies)($promotion, $productTagNames);
    }
}
