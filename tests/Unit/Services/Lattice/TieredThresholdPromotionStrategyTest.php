<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Lattice;

use App\Enums\QualificationRuleKind;
use App\Enums\TieredThresholdDiscountKind;
use App\Models\Promotions\MixAndMatchPromotion;
use App\Models\Promotions\Promotion;
use App\Models\Promotions\TieredThresholdDiscount;
use App\Models\Promotions\TieredThresholdPromotion;
use App\Models\Promotions\TieredThresholdTier;
use App\Services\Lattice\Promotions\TieredThresholdPromotionStrategy;
use RuntimeException;

test('supports tiered threshold promotions only', function (): void {
    $strategy = new TieredThresholdPromotionStrategy;

    $tieredThresholdPromotion = new Promotion;
    $tieredThresholdPromotion->setRelation(
        'promotionable',
        new TieredThresholdPromotion,
    );

    $mixAndMatchPromotion = new Promotion;
    $mixAndMatchPromotion->setRelation(
        'promotionable',
        new MixAndMatchPromotion,
    );

    expect($strategy->supports($tieredThresholdPromotion))->toBeTrue();
    expect($strategy->supports($mixAndMatchPromotion))->toBeFalse();
});

test(
    'builds a lattice tiered threshold promotion with sorted tiers',
    function (): void {
        $strategy = new TieredThresholdPromotionStrategy;

        $tierAQualification = tieredLatticeQualification(id: 801);
        $tierAQualification->setRelation(
            'rules',
            collect([
                tieredLatticeQualificationRule(
                    id: 1,
                    kind: QualificationRuleKind::HasAll,
                    sortOrder: 0,
                    tags: ['a'],
                ),
            ]),
        );

        $tierBQualification = tieredLatticeQualification(id: 802);
        $tierBQualification->setRelation(
            'rules',
            collect([
                tieredLatticeQualificationRule(
                    id: 2,
                    kind: QualificationRuleKind::HasAll,
                    sortOrder: 0,
                    tags: ['b'],
                ),
            ]),
        );

        $amountDiscount = new TieredThresholdDiscount;
        $amountDiscount->kind = TieredThresholdDiscountKind::AmountOffTotal;
        $amountDiscount->amount = 250;
        $amountDiscount->amount_currency = 'GBP';

        $percentageDiscount = new TieredThresholdDiscount;
        $percentageDiscount->kind =
            TieredThresholdDiscountKind::PercentageOffEachItem;
        $percentageDiscount->percentage = 20.0;

        $tierA = new TieredThresholdTier;
        $tierA->id = 10;
        $tierA->sort_order = 2;
        $tierA->lower_monetary_threshold_minor = 500;
        $tierA->lower_monetary_threshold_currency = 'GBP';
        $tierA->upper_item_count_threshold = 4;
        $tierA->setRelation('discount', $amountDiscount);
        $tierA->setRelation('qualification', $tierAQualification);

        $tierB = new TieredThresholdTier;
        $tierB->id = 20;
        $tierB->sort_order = 1;
        $tierB->lower_item_count_threshold = 2;
        $tierB->upper_monetary_threshold_minor = 1200;
        $tierB->upper_monetary_threshold_currency = 'GBP';
        $tierB->setRelation('discount', $percentageDiscount);
        $tierB->setRelation('qualification', $tierBQualification);

        $tieredThreshold = new TieredThresholdPromotion;
        $tieredThreshold->setRelation('tiers', collect([$tierA, $tierB]));

        $promotion = new Promotion;
        $promotion->setRawAttributes(
            [
                'name' => 'Tiered Promo',
                'application_budget' => 15,
                'monetary_budget' => 5000,
            ],
            true,
        );
        $promotion->setRelation('promotionable', $tieredThreshold);
        $promotion->setRelation(
            'qualifications',
            collect([$tierAQualification, $tierBQualification]),
        );

        $latticePromotion = $strategy->make($promotion);

        expect($latticePromotion->tiers)
            ->toHaveCount(2)
            ->and($latticePromotion->tiers[0]->discount->kind->value)
            ->toBe(TieredThresholdDiscountKind::PercentageOffEachItem->value)
            ->and($latticePromotion->tiers[0]->discount->percentage?->value())
            ->toBe(0.2)
            ->and(
                $latticePromotion->tiers[0]->lowerThreshold->itemCountThreshold,
            )
            ->toBe(2)
            ->and(
                $latticePromotion->tiers[0]->upperThreshold?->monetaryThreshold
                    ?->amount,
            )
            ->toBe(1200)
            ->and(
                $latticePromotion->tiers[0]->contributionQualification->matches(
                    ['b'],
                ),
            )
            ->toBeTrue()
            ->and($latticePromotion->tiers[1]->discount->amount?->amount)
            ->toBe(250)
            ->and(
                $latticePromotion->tiers[1]->lowerThreshold->monetaryThreshold
                    ?->amount,
            )
            ->toBe(500)
            ->and(
                $latticePromotion->tiers[1]->upperThreshold
                    ?->itemCountThreshold,
            )
            ->toBe(4)
            ->and($latticePromotion->budget->redemptionLimit)
            ->toBe(15)
            ->and($latticePromotion->budget->monetaryLimit?->amount)
            ->toBe(5000);
    },
);

test(
    'resolves tier qualification from promotion qualifications when tier relation is not loaded',
    function (): void {
        $strategy = new TieredThresholdPromotionStrategy;

        $tier = new TieredThresholdTier;
        $tier->id = 77;
        $tier->sort_order = 0;
        $tier->lower_item_count_threshold = 1;

        $qualification = tieredLatticeQualification(
            id: 901,
            context: 'primary',
            qualifiableType: $tier->getMorphClass(),
            qualifiableId: 77,
        );
        $qualification->setRelation(
            'rules',
            collect([
                tieredLatticeQualificationRule(
                    id: 3,
                    kind: QualificationRuleKind::HasAny,
                    sortOrder: 0,
                    tags: ['x', 'y'],
                ),
            ]),
        );

        $discount = new TieredThresholdDiscount;
        $discount->kind = TieredThresholdDiscountKind::AmountOffTotal;
        $discount->amount = 900;
        $discount->amount_currency = 'USD';

        $tier->setRelation('discount', $discount);

        $tieredThreshold = new TieredThresholdPromotion;
        $tieredThreshold->setRelation('tiers', collect([$tier]));

        $promotion = new Promotion(['name' => 'Tiered']);
        $promotion->setRelation('promotionable', $tieredThreshold);
        $promotion->setRelation('qualifications', collect([$qualification]));

        $latticePromotion = $strategy->make($promotion);

        expect($latticePromotion->tiers[0]->discount->amount?->amount)
            ->toBe(900)
            ->and($latticePromotion->tiers[0]->discount->amount?->currency)
            ->toBe('USD')
            ->and(
                $latticePromotion->tiers[0]->contributionQualification->matches(
                    ['x'],
                ),
            )
            ->toBeTrue();
    },
);

test('throws when tier discount relation is missing', function (): void {
    $strategy = new TieredThresholdPromotionStrategy;

    $tier = new TieredThresholdTier;
    $tier->id = 11;
    $tier->sort_order = 0;
    $tier->lower_item_count_threshold = 1;
    $tier->setRelation('qualification', tieredLatticeQualification(id: 1001));

    $tieredThreshold = new TieredThresholdPromotion;
    $tieredThreshold->setRelation('tiers', collect([$tier]));

    $promotion = new Promotion(['name' => 'Broken Tiered']);
    $promotion->setRelation('promotionable', $tieredThreshold);
    $promotion->setRelation('qualifications', collect([$tier->qualification]));

    expect(fn (): mixed => $strategy->make($promotion))->toThrow(
        RuntimeException::class,
        'Tiered threshold tier [11] is missing its discount relation.',
    );
});

test('throws when tier qualification cannot be resolved', function (): void {
    $strategy = new TieredThresholdPromotionStrategy;

    $tier = new TieredThresholdTier;
    $tier->id = 13;
    $tier->sort_order = 0;
    $tier->lower_item_count_threshold = 1;

    $discount = new TieredThresholdDiscount;
    $discount->kind = TieredThresholdDiscountKind::OverrideEachItem;
    $discount->amount = 100;
    $discount->amount_currency = 'GBP';

    $tier->setRelation('discount', $discount);

    $tieredThreshold = new TieredThresholdPromotion;
    $tieredThreshold->setRelation('tiers', collect([$tier]));

    $promotion = new Promotion(['name' => 'Broken Tier']);
    $promotion->setRelation('promotionable', $tieredThreshold);
    $promotion->setRelation('qualifications', collect());

    expect(fn (): mixed => $strategy->make($promotion))->toThrow(
        RuntimeException::class,
        'Tiered threshold tier [13] is missing its primary qualification.',
    );
});

test('throws when tier lower threshold is missing', function (): void {
    $strategy = new TieredThresholdPromotionStrategy;

    $qualification = tieredLatticeQualification(id: 1101);
    $qualification->setRelation(
        'rules',
        collect([
            tieredLatticeQualificationRule(
                id: 4,
                kind: QualificationRuleKind::HasAll,
                sortOrder: 0,
                tags: ['eligible'],
            ),
        ]),
    );

    $discount = new TieredThresholdDiscount;
    $discount->kind = TieredThresholdDiscountKind::AmountOffTotal;
    $discount->amount = 100;
    $discount->amount_currency = 'GBP';

    $tier = new TieredThresholdTier;
    $tier->id = 15;
    $tier->sort_order = 0;
    $tier->setRelation('discount', $discount);
    $tier->setRelation('qualification', $qualification);

    $tieredThreshold = new TieredThresholdPromotion;
    $tieredThreshold->setRelation('tiers', collect([$tier]));

    $promotion = new Promotion(['name' => 'Missing Threshold']);
    $promotion->setRelation('promotionable', $tieredThreshold);
    $promotion->setRelation('qualifications', collect([$qualification]));

    expect(fn (): mixed => $strategy->make($promotion))->toThrow(
        RuntimeException::class,
        'Tiered threshold tier [15] is missing its lower threshold.',
    );
});

function tieredLatticeQualification(
    int $id,
    string $context = 'primary',
    ?string $qualifiableType = null,
    ?int $qualifiableId = null,
): \App\Models\Promotions\Qualification {
    $qualification = new \App\Models\Promotions\Qualification;
    $qualification->id = $id;
    $qualification->context = $context;
    $qualification->qualifiable_type = $qualifiableType;
    $qualification->qualifiable_id = $qualifiableId;
    $qualification->op = 'and';
    $qualification->setRelation('rules', collect());

    return $qualification;
}

/**
 * @param  string[]  $tags
 */
function tieredLatticeQualificationRule(
    int $id,
    QualificationRuleKind|string $kind,
    int $sortOrder,
    ?int $groupQualificationId = null,
    array $tags = [],
): \App\Models\Promotions\QualificationRule {
    $rule = new \App\Models\Promotions\QualificationRule;
    $rule->id = $id;
    $rule->kind = $kind;
    $rule->sort_order = $sortOrder;
    $rule->group_qualification_id = $groupQualificationId;

    $rule->setRelation(
        'tags',
        collect(
            array_map(
                fn (string $tag): object => (object) ['name' => $tag],
                $tags,
            ),
        ),
    );

    return $rule;
}
