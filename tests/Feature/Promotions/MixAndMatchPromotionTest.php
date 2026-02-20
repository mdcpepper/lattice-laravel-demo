<?php

namespace Tests\Feature\Promotions;

use App\Enums\MixAndMatchDiscountKind;
use App\Enums\QualificationContext;
use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Models\MixAndMatchDiscount;
use App\Models\MixAndMatchPromotion;
use App\Models\MixAndMatchSlot;
use App\Models\Promotion;
use App\Models\Team;
use App\Services\Lattice\Promotions\LatticePromotionFactory;
use Illuminate\Support\Facades\DB;
use Lattice\Item;
use Lattice\Money;
use Lattice\Product;
use Lattice\Stack\Layer;
use Lattice\Stack\LayerOutput;
use Lattice\Stack\StackBuilder;

beforeEach(function (): void {
    $this->team = Team::factory()->create();

    $this->discount = MixAndMatchDiscount::query()->create([
        'kind' => MixAndMatchDiscountKind::OverrideTotal,
        'amount' => 380,
        'amount_currency' => 'GBP',
    ]);

    $this->mixAndMatch = MixAndMatchPromotion::query()->create([
        'mix_and_match_discount_id' => $this->discount->id,
    ]);

    $this->promotion = Promotion::query()->create([
        'name' => '£3.80 Meal Deal',
        'application_budget' => 100,
        'monetary_budget' => 5000_00,
        'team_id' => $this->team->id,
        'promotionable_type' => $this->mixAndMatch->getMorphClass(),
        'promotionable_id' => $this->mixAndMatch->id,
    ]);

    // Slot qualification:
    // AND(
    //   has_all([eligible, member]),
    //   group(OR(
    //     has_any([vip, staff]),
    //     has_none([blocked])
    //   )),
    // )

    $this->slot = $this->mixAndMatch->slots()->create([
        'min' => 1,
        'max' => 1,
        'sort_order' => 0,
    ]);

    $this->rootQualification = $this->slot->qualification()->create([
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
        ->toBeInstanceOf(MixAndMatchPromotion::class)
        ->and($promotion->promotionable->relationLoaded('discount'))
        ->toBeTrue()
        ->and($promotion->promotionable->relationLoaded('slots'))
        ->toBeTrue()
        ->and($promotion->promotionable->discount->id)
        ->toBe($this->discount->id)
        ->and($promotion->promotionable->slots)
        ->toHaveCount(1);

    $this->assertDatabaseHas('mix_and_match_discounts', [
        'id' => $this->discount->id,
        'kind' => MixAndMatchDiscountKind::OverrideTotal->value,
        'amount' => 380,
        'amount_currency' => 'GBP',
    ]);

    $this->assertDatabaseHas('mix_and_match_slots', [
        'id' => $this->slot->id,
        'mix_and_match_promotion_id' => $this->mixAndMatch->id,
        'min' => 1,
        'max' => 1,
    ]);

    $this->assertDatabaseHas('qualifications', [
        'id' => $this->rootQualification->id,
        'promotion_id' => $this->promotion->id,
        'qualifiable_type' => $this->slot->getMorphClass(),
        'qualifiable_id' => $this->slot->id,
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
        ->toBeInstanceOf(MixAndMatchPromotion::class)
        ->and($promotion->promotionable->relationLoaded('discount'))
        ->toBeTrue()
        ->and($promotion->promotionable->relationLoaded('slots'))
        ->toBeTrue()
        ->and($promotion->promotionable->slots->count())
        ->toBe(1)
        ->and(
            $promotion->promotionable->slots->every(
                fn (MixAndMatchSlot $slot): bool => $slot->relationLoaded(
                    'qualification',
                ),
            ),
        )
        ->toBeTrue()
        ->and(
            $promotion->promotionable->slots->every(
                fn (
                    MixAndMatchSlot $slot,
                ): bool => $slot->qualification->rules->every(
                    fn ($rule): bool => $rule->relationLoaded('tags'),
                ),
            ),
        )
        ->toBeTrue();
});

it('can be turned into a Lattice Graph', function (): void {
    $discount = MixAndMatchDiscount::query()->create([
        'kind' => MixAndMatchDiscountKind::OverrideTotal,
        'amount' => 380,
        'amount_currency' => 'GBP',
    ]);

    $mixAndMatch = MixAndMatchPromotion::query()->create([
        'mix_and_match_discount_id' => $discount->id,
    ]);

    $promotion = Promotion::query()->create([
        'name' => '£3.80 Meal Deal',
        'application_budget' => 100,
        'monetary_budget' => 5000_00,
        'team_id' => $this->team->id,
        'promotionable_type' => $mixAndMatch->getMorphClass(),
        'promotionable_id' => $mixAndMatch->id,
    ]);

    foreach (
        ['meal-deal:main', 'meal-deal:snack', 'meal-deal:drink'] as $sortOrder => $tag
    ) {
        $slot = $mixAndMatch->slots()->create([
            'min' => 1,
            'max' => 1,
            'sort_order' => $sortOrder,
        ]);

        $qualification = $slot->qualification()->create([
            'promotion_id' => $promotion->id,
            'context' => QualificationContext::Primary->value,
            'op' => QualificationOp::And,
            'sort_order' => 0,
        ]);

        $rule = $qualification->rules()->create([
            'kind' => QualificationRuleKind::HasAll,
            'sort_order' => 0,
        ]);

        $rule->syncTags([$tag]);
    }

    $promotion = Promotion::query()->withGraph()->findOrFail($promotion->id);

    $latticePromotion = app(LatticePromotionFactory::class)->make($promotion);

    expect($latticePromotion)
        ->toBeInstanceOf(\Lattice\Promotion\MixAndMatch\MixAndMatch::class)
        ->and($latticePromotion->reference)
        ->toBeInstanceOf(Promotion::class)
        ->and($latticePromotion->reference->is($promotion))
        ->toBeTrue()
        ->and($latticePromotion->slots)
        ->toHaveCount(3)
        ->and(
            $latticePromotion->slots[0]->qualification->matches([
                'meal-deal:main',
            ]),
        )
        ->toBeTrue()
        ->and(
            $latticePromotion->slots[1]->qualification->matches([
                'meal-deal:snack',
            ]),
        )
        ->toBeTrue()
        ->and(
            $latticePromotion->slots[2]->qualification->matches([
                'meal-deal:drink',
            ]),
        )
        ->toBeTrue()
        ->and(
            $latticePromotion->slots[2]->qualification->matches([
                'meal-deal:snack',
            ]),
        )
        ->toBeFalse()
        ->and($latticePromotion->discount->kind->value)
        ->toBe(MixAndMatchDiscountKind::OverrideTotal->value)
        ->and($latticePromotion->discount->amount?->amount)
        ->toBe(380)
        ->and($latticePromotion->budget->redemptionLimit)
        ->toBe(100)
        ->and($latticePromotion->budget->monetaryLimit?->amount)
        ->toBe(500000);

    $mainItem = Item::fromProduct(
        reference: 'main-item',
        product: new Product('product-1', 'Main', new Money(2_50, 'GBP'), [
            'meal-deal:main',
        ]),
    );

    $sideItem = Item::fromProduct(
        reference: 'side-item',
        product: new Product('product-2', 'Side', new Money(1_75, 'GBP'), [
            'meal-deal:snack',
        ]),
    );

    $drinkItem = Item::fromProduct(
        reference: 'drink-item',
        product: new Product('product-3', 'Drink', new Money(1_25, 'GBP'), [
            'meal-deal:drink',
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
        ->process([$mainItem, $sideItem, $drinkItem]);

    expect($receipt->subtotal->amount)
        ->toBe(550)
        ->toBeGreaterThan(380)
        ->and($receipt->total->amount)
        ->toBe(380)
        ->and($receipt->promotionRedemptions)
        ->toHaveCount(3)
        ->and(
            collect($receipt->promotionRedemptions)->every(
                fn ($application): bool => $application->promotion
                    ->reference instanceof Promotion &&
                    $application->promotion->reference->is($promotion),
            ),
        )
        ->toBeTrue()
        ->and(
            collect($receipt->promotionRedemptions)->sum(
                fn ($application): int => $application->finalPrice->amount,
            ),
        )
        ->toBe(380)
        ->and($receipt->subtotal->amount - $receipt->total->amount)
        ->toBe(170);
});
