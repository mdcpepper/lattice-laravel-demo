<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Lattice;

use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Enums\SimpleDiscountKind;
use App\Models\DirectDiscountPromotion;
use App\Models\MixAndMatchPromotion;
use App\Models\Promotion;
use App\Models\SimpleDiscount;
use App\Services\Lattice\DirectDiscountPromotionStrategy;
use RuntimeException;

test('supports direct discount promotions only', function (): void {
    $strategy = new DirectDiscountPromotionStrategy;

    $directPromotion = new Promotion;
    $directPromotion->setRelation(
        'promotionable',
        new DirectDiscountPromotion,
    );

    $mixAndMatchPromotion = new Promotion;
    $mixAndMatchPromotion->setRelation(
        'promotionable',
        new MixAndMatchPromotion,
    );

    expect($strategy->supports($directPromotion))->toBeTrue();
    expect($strategy->supports($mixAndMatchPromotion))->toBeFalse();
});

test(
    'builds a lattice direct discount promotion from loaded relations',
    function (): void {
        $strategy = new DirectDiscountPromotionStrategy;

        $nestedQualification = qualification(id: 102, op: QualificationOp::Or);

        $rootQualification = qualification(id: 101, op: QualificationOp::And);

        $rootQualification->setRelation(
            'rules',
            collect([
                qualificationRule(
                    id: 1,
                    kind: QualificationRuleKind::HasAll,
                    sortOrder: 0,
                    tags: ['eligible', 'member'],
                ),
                qualificationRule(
                    id: 2,
                    kind: QualificationRuleKind::Group,
                    sortOrder: 1,
                    groupQualificationId: 102,
                ),
            ]),
        );

        $nestedQualification->setRelation(
            'rules',
            collect([
                qualificationRule(
                    id: 3,
                    kind: QualificationRuleKind::HasAny,
                    sortOrder: 0,
                    tags: ['vip', 'staff'],
                ),
                qualificationRule(
                    id: 4,
                    kind: QualificationRuleKind::HasNone,
                    sortOrder: 1,
                    tags: ['blocked'],
                ),
            ]),
        );

        $discount = new SimpleDiscount;
        $discount->kind = SimpleDiscountKind::PercentageOff;
        $discount->percentage = 10.0;

        $directPromotion = new DirectDiscountPromotion;
        $directPromotion->id = 55;
        $directPromotion->setRelation('discount', $discount);
        $directPromotion->setRelation('qualification', $rootQualification);

        $promotion = new Promotion;
        $promotion->setRawAttributes(
            [
                'name' => '10% Off',
                'application_budget' => 12,
                'monetary_budget' => 2500,
            ],
            true,
        );
        $promotion->setRelation('promotionable', $directPromotion);
        $promotion->setRelation(
            'qualifications',
            collect([$rootQualification, $nestedQualification]),
        );

        $latticePromotion = $strategy->make($promotion);

        expect($latticePromotion->reference)
            ->toBe($promotion)
            ->and(
                $latticePromotion->qualification->matches([
                    'eligible',
                    'member',
                    'vip',
                ]),
            )
            ->toBeTrue()
            ->and(
                $latticePromotion->qualification->matches([
                    'eligible',
                    'member',
                    'blocked',
                ]),
            )
            ->toBeFalse()
            ->and($latticePromotion->discount->percentage?->value())
            ->toBe(0.1)
            ->and($latticePromotion->budget->applicationLimit)
            ->toBe(12)
            ->and($latticePromotion->budget->monetaryLimit?->amount)
            ->toBe(2500);
    },
);

test(
    'resolves direct qualification from promotion qualifications when direct relation is not loaded',
    function (): void {
        $strategy = new DirectDiscountPromotionStrategy;

        $directPromotion = new DirectDiscountPromotion;
        $directPromotion->id = 88;

        $rootQualification = qualification(
            id: 201,
            context: 'primary',
            qualifiableType: $directPromotion->getMorphClass(),
            qualifiableId: 88,
            op: QualificationOp::And,
        );
        $rootQualification->setRelation(
            'rules',
            collect([
                qualificationRule(
                    id: 5,
                    kind: QualificationRuleKind::HasAll,
                    sortOrder: 0,
                    tags: ['category-a'],
                ),
            ]),
        );

        $discount = new SimpleDiscount;
        $discount->kind = SimpleDiscountKind::AmountOff;
        $discount->amount = 500;
        $discount->amount_currency = 'USD';

        $directPromotion->setRelation('discount', $discount);

        $promotion = new Promotion(['name' => 'Amount Off']);
        $promotion->setRelation('promotionable', $directPromotion);
        $promotion->setRelation(
            'qualifications',
            collect([$rootQualification]),
        );

        $latticePromotion = $strategy->make($promotion);

        expect($latticePromotion->qualification->matches(['category-a']))
            ->toBeTrue()
            ->and($latticePromotion->discount->amount?->amount)
            ->toBe(500)
            ->and($latticePromotion->discount->amount?->currency)
            ->toBe('USD');
    },
);

test('throws when direct discount relation is missing', function (): void {
    $strategy = new DirectDiscountPromotionStrategy;

    $directPromotion = new DirectDiscountPromotion;
    $directPromotion->id = 89;

    $promotion = new Promotion(['name' => 'Broken Promotion']);
    $promotion->setRelation('promotionable', $directPromotion);
    $promotion->setRelation('qualifications', collect());

    expect(fn (): mixed => $strategy->make($promotion))->toThrow(
        RuntimeException::class,
        'Direct discount promotion is missing its simple discount relation.',
    );
});

test('throws when direct qualification cannot be resolved', function (): void {
    $strategy = new DirectDiscountPromotionStrategy;

    $discount = new SimpleDiscount;
    $discount->kind = SimpleDiscountKind::PercentageOff;
    $discount->percentage = 15.0;

    $directPromotion = new DirectDiscountPromotion;
    $directPromotion->id = 90;
    $directPromotion->setRelation('discount', $discount);

    $promotion = new Promotion(['name' => 'Broken Qualification']);
    $promotion->setRelation('promotionable', $directPromotion);
    $promotion->setRelation('qualifications', collect());

    expect(fn (): mixed => $strategy->make($promotion))->toThrow(
        RuntimeException::class,
        'Direct discount promotion is missing its primary qualification.',
    );
});
