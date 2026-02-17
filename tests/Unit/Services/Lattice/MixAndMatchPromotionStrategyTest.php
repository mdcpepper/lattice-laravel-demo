<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Lattice;

use App\Enums\MixAndMatchDiscountKind;
use App\Enums\QualificationRuleKind;
use App\Models\DirectDiscountPromotion;
use App\Models\MixAndMatchDiscount;
use App\Models\MixAndMatchPromotion;
use App\Models\MixAndMatchSlot;
use App\Models\Promotion;
use App\Services\Lattice\Promotions\MixAndMatchPromotionStrategy;
use RuntimeException;

test('supports mix and match promotions only', function (): void {
    $strategy = new MixAndMatchPromotionStrategy;

    $mixAndMatchPromotion = new Promotion;
    $mixAndMatchPromotion->setRelation(
        'promotionable',
        new MixAndMatchPromotion,
    );

    $directPromotion = new Promotion;
    $directPromotion->setRelation(
        'promotionable',
        new DirectDiscountPromotion,
    );

    expect($strategy->supports($mixAndMatchPromotion))->toBeTrue();
    expect($strategy->supports($directPromotion))->toBeFalse();
});

test(
    'builds a lattice mix and match promotion with sorted slots',
    function (): void {
        $strategy = new MixAndMatchPromotionStrategy;

        $slotAQualification = qualification(id: 401);
        $slotAQualification->setRelation(
            'rules',
            collect([
                qualificationRule(
                    id: 1,
                    kind: QualificationRuleKind::HasAll,
                    sortOrder: 0,
                    tags: ['a'],
                ),
            ]),
        );

        $slotBQualification = qualification(id: 402);
        $slotBQualification->setRelation(
            'rules',
            collect([
                qualificationRule(
                    id: 2,
                    kind: QualificationRuleKind::HasAll,
                    sortOrder: 0,
                    tags: ['b'],
                ),
            ]),
        );

        $slotA = new MixAndMatchSlot;
        $slotA->id = 10;
        $slotA->sort_order = 2;
        $slotA->min = 1;
        $slotA->max = 2;
        $slotA->setRelation('qualification', $slotAQualification);

        $slotB = new MixAndMatchSlot;
        $slotB->id = 20;
        $slotB->sort_order = 1;
        $slotB->min = 2;
        $slotB->max = null;
        $slotB->setRelation('qualification', $slotBQualification);

        $discount = new MixAndMatchDiscount;
        $discount->kind = MixAndMatchDiscountKind::PercentageOffCheapest;
        $discount->percentage = 20.0;

        $mixAndMatch = new MixAndMatchPromotion;
        $mixAndMatch->setRelation('discount', $discount);
        $mixAndMatch->setRelation('slots', collect([$slotA, $slotB]));

        $promotion = new Promotion;
        $promotion->setRawAttributes(
            [
                'name' => 'Mix Promo',
                'monetary_budget' => 1200,
            ],
            true,
        );
        $promotion->setRelation('promotionable', $mixAndMatch);
        $promotion->setRelation(
            'qualifications',
            collect([$slotAQualification, $slotBQualification]),
        );

        $latticePromotion = $strategy->make($promotion);

        expect($latticePromotion->slots)
            ->toHaveCount(2)
            ->and($latticePromotion->slots[0]->reference)
            ->toBe($slotB)
            ->and($latticePromotion->slots[0]->min)
            ->toBe(2)
            ->and($latticePromotion->slots[0]->max)
            ->toBeNull()
            ->and($latticePromotion->slots[0]->qualification->matches(['b']))
            ->toBeTrue()
            ->and($latticePromotion->discount->percentage?->value())
            ->toBe(0.2)
            ->and($latticePromotion->budget->applicationLimit)
            ->toBeNull()
            ->and($latticePromotion->budget->monetaryLimit?->amount)
            ->toBe(1200);
    },
);

test(
    'resolves slot qualification from promotion qualifications when slot relation is not loaded',
    function (): void {
        $strategy = new MixAndMatchPromotionStrategy;

        $slot = new MixAndMatchSlot;
        $slot->id = 77;
        $slot->sort_order = 0;
        $slot->min = 1;
        $slot->max = 1;

        $slotQualification = qualification(
            id: 501,
            context: 'primary',
            qualifiableType: $slot->getMorphClass(),
            qualifiableId: 77,
        );
        $slotQualification->setRelation(
            'rules',
            collect([
                qualificationRule(
                    id: 3,
                    kind: QualificationRuleKind::HasAny,
                    sortOrder: 0,
                    tags: ['x', 'y'],
                ),
            ]),
        );

        $discount = new MixAndMatchDiscount;
        $discount->kind = MixAndMatchDiscountKind::AmountOffTotal;
        $discount->amount = 900;
        $discount->amount_currency = 'USD';

        $mixAndMatch = new MixAndMatchPromotion;
        $mixAndMatch->setRelation('discount', $discount);
        $mixAndMatch->setRelation('slots', collect([$slot]));

        $promotion = new Promotion(['name' => 'Mix Promo']);
        $promotion->setRelation('promotionable', $mixAndMatch);
        $promotion->setRelation(
            'qualifications',
            collect([$slotQualification]),
        );

        $latticePromotion = $strategy->make($promotion);

        expect($latticePromotion->slots[0]->qualification->matches(['x']))
            ->toBeTrue()
            ->and($latticePromotion->discount->amount?->amount)
            ->toBe(900)
            ->and($latticePromotion->discount->amount?->currency)
            ->toBe('USD');
    },
);

test(
    'throws when mix and match discount relation is missing',
    function (): void {
        $strategy = new MixAndMatchPromotionStrategy;

        $mixAndMatch = new MixAndMatchPromotion;
        $mixAndMatch->setRelation('slots', collect());

        $promotion = new Promotion(['name' => 'Broken Mix']);
        $promotion->setRelation('promotionable', $mixAndMatch);
        $promotion->setRelation('qualifications', collect());

        expect(fn (): mixed => $strategy->make($promotion))->toThrow(
            RuntimeException::class,
            'Mix and match promotion is missing its discount relation.',
        );
    },
);

test('throws when slot qualification cannot be resolved', function (): void {
    $strategy = new MixAndMatchPromotionStrategy;

    $slot = new MixAndMatchSlot;
    $slot->id = 13;
    $slot->sort_order = 0;
    $slot->min = 1;
    $slot->max = 2;

    $discount = new MixAndMatchDiscount;
    $discount->kind = MixAndMatchDiscountKind::OverrideEachItem;
    $discount->amount = 100;
    $discount->amount_currency = 'GBP';

    $mixAndMatch = new MixAndMatchPromotion;
    $mixAndMatch->setRelation('discount', $discount);
    $mixAndMatch->setRelation('slots', collect([$slot]));

    $promotion = new Promotion(['name' => 'Broken Slot']);
    $promotion->setRelation('promotionable', $mixAndMatch);
    $promotion->setRelation('qualifications', collect());

    expect(fn (): mixed => $strategy->make($promotion))->toThrow(
        RuntimeException::class,
        'Mix and match slot [13] is missing its primary qualification.',
    );
});
