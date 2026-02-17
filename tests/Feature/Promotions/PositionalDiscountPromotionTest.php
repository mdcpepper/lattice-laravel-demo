<?php

namespace Tests\Feature\Promotions;

use App\Enums\QualificationContext;
use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Enums\SimpleDiscountKind;
use App\Models\PositionalDiscountPromotion;
use App\Models\Promotion;
use App\Models\SimpleDiscount;
use App\Services\Lattice\LatticePromotionFactory;
use Illuminate\Support\Facades\DB;
use Lattice\Item;
use Lattice\Layer;
use Lattice\LayerOutput;
use Lattice\Money;
use Lattice\Product;
use Lattice\StackBuilder;

beforeEach(function (): void {
    $this->discount = SimpleDiscount::query()->create([
        'kind' => SimpleDiscountKind::PercentageOff,
        'percentage' => 100.0,
    ]);

    $this->positional = PositionalDiscountPromotion::query()->create([
        'simple_discount_id' => $this->discount->id,
        'size' => 2,
    ]);

    $this->position = $this->positional->positions()->create([
        'position' => 1,
        'sort_order' => 0,
    ]);

    $this->promotion = Promotion::query()->create([
        'name' => 'BOGOF',
        'application_budget' => 100,
        'monetary_budget' => 5000_00,
        'promotionable_type' => $this->positional->getMorphClass(),
        'promotionable_id' => $this->positional->id,
    ]);

    // AND(
    //   has_all([eligible, member]),
    //   group(OR(
    //     has_any([vip, staff]),
    //     has_none([blocked])
    //   )),
    // )

    $this->rootQualification = $this->positional->qualification()->create([
        'promotion_id' => $this->promotion->id,
        'context' => QualificationContext::Primary->value,
        'op' => QualificationOp::And,
        'sort_order' => 0,
    ]);

    $this->hasAllRule = $this->rootQualification->rules()->create([
        'kind' => QualificationRuleKind::HasAll,
        'sort_order' => 0,
    ]);

    $this->nestedGroup = $this->promotion->qualifications()->create([
        'parent_qualification_id' => $this->rootQualification->id,
        'context' => QualificationContext::Group->value,
        'op' => QualificationOp::Or,
        'sort_order' => 0,
    ]);

    $this->groupRule = $this->rootQualification->rules()->create([
        'kind' => QualificationRuleKind::Group,
        'group_qualification_id' => $this->nestedGroup->id,
        'sort_order' => 1,
    ]);

    $this->hasAnyRule = $this->nestedGroup->rules()->create([
        'kind' => QualificationRuleKind::HasAny,
        'sort_order' => 0,
    ]);

    $this->hasNoneRule = $this->nestedGroup->rules()->create([
        'kind' => QualificationRuleKind::HasNone,
        'sort_order' => 1,
    ]);

    $this->hasAllRule->syncTags(['eligible', 'member']);
    $this->hasAnyRule->syncTags(['vip', 'staff']);
    $this->hasNoneRule->syncTags(['blocked']);
});

it('can be created and persisted', function (): void {
    $promotion = Promotion::query()->withGraph()->firstOrFail();

    expect($promotion->relationLoaded('promotionable'))
        ->toBeTrue()
        ->and($promotion->promotionable)
        ->toBeInstanceOf(PositionalDiscountPromotion::class)
        ->and($promotion->promotionable->relationLoaded('discount'))
        ->toBeTrue()
        ->and($promotion->promotionable->relationLoaded('qualification'))
        ->toBeTrue()
        ->and($promotion->promotionable->relationLoaded('positions'))
        ->toBeTrue()
        ->and($promotion->promotionable->discount->id)
        ->toBe($this->discount->id)
        ->and($promotion->promotionable->positions)
        ->toHaveCount(1);

    $this->assertDatabaseHas('simple_discounts', [
        'id' => $this->discount->id,
        'kind' => SimpleDiscountKind::PercentageOff->value,
        'percentage' => 10000,
    ]);

    $this->assertDatabaseHas('positional_discount_promotions', [
        'id' => $this->positional->id,
        'simple_discount_id' => $this->discount->id,
        'size' => 2,
    ]);

    $this->assertDatabaseHas('positional_discount_positions', [
        'id' => $this->position->id,
        'positional_discount_promotion_id' => $this->positional->id,
        'position' => 1,
        'sort_order' => 0,
    ]);

    $this->assertDatabaseHas('qualifications', [
        'id' => $this->rootQualification->id,
        'promotion_id' => $this->promotion->id,
        'qualifiable_type' => $this->positional->getMorphClass(),
        'qualifiable_id' => $this->positional->id,
        'context' => QualificationContext::Primary->value,
        'op' => QualificationOp::And->value,
    ]);

    $this->assertDatabaseHas('qualifications', [
        'id' => $this->nestedGroup->id,
        'promotion_id' => $this->promotion->id,
        'parent_qualification_id' => $this->rootQualification->id,
        'context' => QualificationContext::Group->value,
        'op' => QualificationOp::Or->value,
    ]);

    $this->assertDatabaseHas('qualification_rules', [
        'id' => $this->hasAllRule->id,
        'qualification_id' => $this->rootQualification->id,
        'kind' => QualificationRuleKind::HasAll->value,
    ]);

    $this->assertDatabaseHas('qualification_rules', [
        'id' => $this->groupRule->id,
        'qualification_id' => $this->rootQualification->id,
        'kind' => QualificationRuleKind::Group->value,
        'group_qualification_id' => $this->nestedGroup->id,
    ]);

    $this->assertDatabaseHas('qualification_rules', [
        'id' => $this->hasAnyRule->id,
        'qualification_id' => $this->nestedGroup->id,
        'kind' => QualificationRuleKind::HasAny->value,
    ]);

    $this->assertDatabaseHas('qualification_rules', [
        'id' => $this->hasNoneRule->id,
        'qualification_id' => $this->nestedGroup->id,
        'kind' => QualificationRuleKind::HasNone->value,
    ]);
});

it('can be fetched efficiently', function (): void {
    DB::enableQueryLog();

    $existingQueries = count(DB::getQueryLog());

    $promotion = Promotion::query()->withGraph()->firstOrFail();

    expect(count(DB::getQueryLog()) - $existingQueries)
        ->toBeLessThanOrEqual(12)
        ->and($promotion->relationLoaded('promotionable'))
        ->toBeTrue()
        ->and($promotion->relationLoaded('qualifications'))
        ->toBeTrue()
        ->and($promotion->qualifications->count())
        ->toBe(2)
        ->and(
            $promotion->qualifications->every(
                fn ($qualification): bool => $qualification->relationLoaded(
                    'rules',
                ),
            ),
        )
        ->toBeTrue();

    expect($promotion->promotionable)
        ->toBeInstanceOf(PositionalDiscountPromotion::class)
        ->and($promotion->promotionable->relationLoaded('discount'))
        ->toBeTrue()
        ->and($promotion->promotionable->relationLoaded('qualification'))
        ->toBeTrue()
        ->and($promotion->promotionable->relationLoaded('positions'))
        ->toBeTrue()
        ->and($promotion->promotionable->positions->count())
        ->toBe(1)
        ->and(
            $promotion->promotionable->qualification->rules->every(
                fn ($rule): bool => $rule->relationLoaded('tags'),
            ),
        )
        ->toBeTrue();
});

it('can be turned into a Lattice Graph', function (): void {
    $promotion = Promotion::query()->withGraph()->firstOrFail();

    $latticePromotion = app(LatticePromotionFactory::class)->make($promotion);

    expect($latticePromotion)
        ->toBeInstanceOf(\Lattice\Promotions\PositionalDiscountPromotion::class)
        ->and($latticePromotion->reference)
        ->toBeInstanceOf(Promotion::class)
        ->and($latticePromotion->reference->is($promotion))
        ->toBeTrue()
        ->and($latticePromotion->size)
        ->toBe(2)
        ->and($latticePromotion->positions)
        ->toBe([1])
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
        ->toBe(1.0)
        ->and($latticePromotion->budget->applicationLimit)
        ->toBe(100)
        ->and($latticePromotion->budget->monetaryLimit?->amount)
        ->toBe(500000);

    $eligibleItemOne = Item::fromProduct(
        reference: 'eligible-item-one',
        product: new Product('product-1', 'Product', new Money(3_00, 'GBP'), [
            'eligible',
            'member',
            'vip',
        ]),
    );

    $eligibleItemTwo = Item::fromProduct(
        reference: 'eligible-item-two',
        product: new Product('product-2', 'Product', new Money(3_00, 'GBP'), [
            'eligible',
            'member',
            'vip',
        ]),
    );

    $blockedItem = Item::fromProduct(
        reference: 'blocked-item',
        product: new Product('product-3', 'Product', new Money(3_00, 'GBP'), [
            'eligible',
            'member',
            'blocked',
        ]),
    );

    $stackBuilder = new StackBuilder;

    $layer = $stackBuilder->addLayer(
        new Layer(
            reference: 'root',
            output: LayerOutput::passThrough(),
            promotions: [$latticePromotion],
        ),
    );

    $stackBuilder->setRoot($layer);

    $receipt = $stackBuilder
        ->build()
        ->process([$eligibleItemOne, $eligibleItemTwo, $blockedItem]);

    expect($receipt->subtotal->amount)
        ->toBe(900)
        ->and($receipt->total->amount)
        ->toBe(600)
        ->and($receipt->promotionApplications)
        ->toHaveCount(2)
        ->and(
            collect($receipt->promotionApplications)->every(
                fn ($application): bool => $application->promotion
                    ->reference instanceof Promotion &&
                    $application->promotion->reference->is($promotion),
            ),
        )
        ->toBeTrue()
        ->and(
            collect($receipt->promotionApplications)->sum(
                fn ($application): int => $application->finalPrice->amount,
            ),
        )
        ->toBe(300)
        ->and($receipt->subtotal->amount - $receipt->total->amount)
        ->toBe(300);
});
