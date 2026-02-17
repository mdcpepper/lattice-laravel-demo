<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Lattice;

use App\Enums\QualificationContext;
use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Enums\SimpleDiscountKind;
use App\Models\MixAndMatchPromotion;
use App\Models\PositionalDiscountPosition;
use App\Models\PositionalDiscountPromotion;
use App\Models\Promotion;
use App\Models\Qualification;
use App\Models\QualificationRule;
use App\Models\SimpleDiscount;
use App\Services\Lattice\PositionalDiscountPromotionStrategy;
use RuntimeException;

test('supports positional discount promotions only', function (): void {
    $strategy = new PositionalDiscountPromotionStrategy;

    $positionalPromotion = new Promotion;
    $positionalPromotion->setRelation(
        'promotionable',
        new PositionalDiscountPromotion,
    );

    $mixAndMatchPromotion = new Promotion;
    $mixAndMatchPromotion->setRelation(
        'promotionable',
        new MixAndMatchPromotion,
    );

    expect($strategy->supports($positionalPromotion))->toBeTrue();
    expect($strategy->supports($mixAndMatchPromotion))->toBeFalse();
});

test(
    'builds a lattice positional discount promotion from loaded relations',
    function (): void {
        $strategy = new PositionalDiscountPromotionStrategy;

        $nestedQualification = positionalQualification(
            id: 102,
            op: QualificationOp::Or,
        );

        $rootQualification = positionalQualification(
            id: 101,
            op: QualificationOp::And,
        );

        $rootQualification->setRelation(
            'rules',
            collect([
                positionalQualificationRule(
                    id: 1,
                    kind: QualificationRuleKind::HasAll,
                    sortOrder: 0,
                    tags: ['eligible', 'member'],
                ),
                positionalQualificationRule(
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
                positionalQualificationRule(
                    id: 3,
                    kind: QualificationRuleKind::HasAny,
                    sortOrder: 0,
                    tags: ['vip', 'staff'],
                ),
                positionalQualificationRule(
                    id: 4,
                    kind: QualificationRuleKind::HasNone,
                    sortOrder: 1,
                    tags: ['blocked'],
                ),
            ]),
        );

        $position = new PositionalDiscountPosition;
        $position->id = 77;
        $position->sort_order = 0;
        $position->position = 1;

        $discount = new SimpleDiscount;
        $discount->kind = SimpleDiscountKind::PercentageOff;
        $discount->percentage = 10.0;

        $positionalPromotion = new PositionalDiscountPromotion;
        $positionalPromotion->id = 55;
        $positionalPromotion->size = 2;
        $positionalPromotion->setRelation('discount', $discount);
        $positionalPromotion->setRelation('qualification', $rootQualification);
        $positionalPromotion->setRelation('positions', collect([$position]));

        $promotion = new Promotion;

        $promotion->setRawAttributes(
            [
                'name' => '10% Off',
                'application_budget' => 12,
                'monetary_budget' => 2500,
            ],
            true,
        );

        $promotion->setRelation('promotionable', $positionalPromotion);

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
    'resolves positional qualification from promotion qualifications when direct relation is not loaded',
    function (): void {
        $strategy = new PositionalDiscountPromotionStrategy;

        $position = new PositionalDiscountPosition;
        $position->id = 77;
        $position->sort_order = 0;
        $position->position = 1;

        $positionalPromotion = new PositionalDiscountPromotion;
        $positionalPromotion->id = 88;
        $positionalPromotion->size = 2;
        $positionalPromotion->setRelation('positions', collect([$position]));

        $rootQualification = positionalQualification(
            id: 201,
            context: 'primary',
            qualifiableType: $positionalPromotion->getMorphClass(),
            qualifiableId: 88,
            op: QualificationOp::And,
        );
        $rootQualification->setRelation(
            'rules',
            collect([
                positionalQualificationRule(
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

        $positionalPromotion->setRelation('discount', $discount);

        $promotion = new Promotion(['name' => 'Amount Off']);
        $promotion->setRelation('promotionable', $positionalPromotion);
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

test('throws when simple discount relation is missing', function (): void {
    $strategy = new PositionalDiscountPromotionStrategy;

    $position = new PositionalDiscountPosition;
    $position->id = 77;
    $position->sort_order = 0;
    $position->position = 1;

    $positionalPromotion = new PositionalDiscountPromotion;
    $positionalPromotion->id = 89;
    $positionalPromotion->size = 2;

    $positionalPromotion->setRelation('positions', collect([$position]));

    $promotion = new Promotion(['name' => 'Broken Promotion']);
    $promotion->setRelation('promotionable', $positionalPromotion);
    $promotion->setRelation('qualifications', collect());

    expect(fn (): mixed => $strategy->make($promotion))->toThrow(
        RuntimeException::class,
        'Positional discount promotion is missing its simple discount relation.',
    );
});

test(
    'throws when positional qualification cannot be resolved',
    function (): void {
        $strategy = new PositionalDiscountPromotionStrategy;

        $discount = new SimpleDiscount;
        $discount->kind = SimpleDiscountKind::PercentageOff;
        $discount->percentage = 15.0;

        $directPromotion = new PositionalDiscountPromotion;
        $directPromotion->id = 90;
        $directPromotion->setRelation('discount', $discount);

        $promotion = new Promotion(['name' => 'Broken Qualification']);
        $promotion->setRelation('promotionable', $directPromotion);
        $promotion->setRelation('qualifications', collect());

        expect(fn (): mixed => $strategy->make($promotion))->toThrow(
            RuntimeException::class,
            'Positional discount promotion is missing its primary qualification.',
        );
    },
);

function positionalQualification(
    int $id,
    string $context = QualificationContext::Primary->value,
    ?string $qualifiableType = null,
    ?int $qualifiableId = null,
    QualificationOp|string $op = QualificationOp::And,
): Qualification {
    $qualification = new Qualification;

    $qualification->id = $id;
    $qualification->context = $context;
    $qualification->qualifiable_type = $qualifiableType;
    $qualification->qualifiable_id = $qualifiableId;
    $qualification->op = $op;

    $qualification->setRelation('rules', collect());

    return $qualification;
}

/**
 * @param  string[]  $tags
 */
function positionalQualificationRule(
    int $id,
    QualificationRuleKind|string $kind,
    int $sortOrder,
    ?int $groupQualificationId = null,
    array $tags = [],
): QualificationRule {
    $rule = new QualificationRule;

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
