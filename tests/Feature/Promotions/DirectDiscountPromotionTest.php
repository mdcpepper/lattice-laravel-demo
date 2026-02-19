<?php

namespace Tests\Feature\Promotions;

use App\Enums\QualificationContext;
use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Enums\SimpleDiscountKind;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DirectDiscountPromotion;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\PromotionStack;
use App\Models\SimpleDiscount;
use App\Models\Team;
use App\Services\Lattice\Promotions\LatticePromotionFactory;
use Illuminate\Support\Facades\DB;
use Lattice\Item;
use Lattice\Layer;
use Lattice\LayerOutput;
use Lattice\Money;
use Lattice\Product;
use Lattice\StackBuilder;

beforeEach(function (): void {
    $this->team = Team::factory()->create();

    $this->discount = SimpleDiscount::query()->create([
        'kind' => SimpleDiscountKind::PercentageOff,
        'percentage' => 10.0,
    ]);

    $this->direct = DirectDiscountPromotion::query()->create([
        'simple_discount_id' => $this->discount->id,
    ]);

    $this->promotion = Promotion::query()->create([
        'name' => '10% Off Eligible Items',
        'application_budget' => 100,
        'monetary_budget' => 5000_00,
        'team_id' => $this->team->id,
        'promotionable_type' => $this->direct->getMorphClass(),
        'promotionable_id' => $this->direct->id,
    ]);

    // AND(
    //   has_all([eligible, member]),
    //   group(OR(
    //     has_any([vip, staff]),
    //     has_none([blocked])
    //   )),
    // )

    $this->rootQualification = $this->direct->qualification()->create([
        'promotion_id' => $this->promotion->id,
        'context' => 'primary',
        'op' => QualificationOp::And,
        'sort_order' => 0,
    ]);

    $this->hasAllRule = $this->rootQualification->rules()->create([
        'kind' => QualificationRuleKind::HasAll,
        'sort_order' => 0,
    ]);

    $this->nestedGroup = $this->promotion->qualifications()->create([
        'parent_qualification_id' => $this->rootQualification->id,
        'context' => 'group',
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
        ->toBeInstanceOf(DirectDiscountPromotion::class)
        ->and($promotion->promotionable->relationLoaded('discount'))
        ->toBeTrue()
        ->and($promotion->promotionable->discount->id)
        ->toBe($this->discount->id);

    $this->assertDatabaseHas('simple_discounts', [
        'id' => $this->discount->id,
        'kind' => SimpleDiscountKind::PercentageOff->value,
        'percentage' => 1000,
    ]);

    $this->assertDatabaseHas('qualifications', [
        'id' => $this->rootQualification->id,
        'promotion_id' => $this->promotion->id,
        'qualifiable_type' => $this->direct->getMorphClass(),
        'qualifiable_id' => $this->direct->id,
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
        ->toBeLessThanOrEqual(11)
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
        ->toBeInstanceOf(DirectDiscountPromotion::class)
        ->and($promotion->promotionable->relationLoaded('discount'))
        ->toBeTrue()
        ->and($promotion->promotionable->relationLoaded('qualification'))
        ->toBeTrue()
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
        ->toBeInstanceOf(\Lattice\Promotions\DirectDiscountPromotion::class)
        ->and($latticePromotion->reference)
        ->toBeInstanceOf(Promotion::class)
        ->and($latticePromotion->reference->is($promotion))
        ->toBeTrue()
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
        ->and($latticePromotion->budget->redemptionLimit)
        ->toBe(100)
        ->and($latticePromotion->budget->monetaryLimit?->amount)
        ->toBe(500000);

    $eligibleItem = Item::fromProduct(
        reference: 'eligible-item',
        product: new Product('product-1', 'Product', new Money(3_00, 'GBP'), [
            'eligible',
            'member',
            'vip',
        ]),
    );

    $ineligibleItem = Item::fromProduct(
        reference: 'ineligible-item',
        product: new Product('product-2', 'Product', new Money(3_00, 'GBP'), [
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
        ->process([$eligibleItem, $ineligibleItem]);

    expect($receipt->subtotal->amount)
        ->toBe(600)
        ->and($receipt->total->amount)
        ->toBe(570)
        ->and($receipt->promotionRedemptions)
        ->toHaveCount(1)
        ->and($receipt->promotionRedemptions[0]->promotion->reference)
        ->toBeInstanceOf(Promotion::class)
        ->and(
            $receipt->promotionRedemptions[0]->promotion->reference->is(
                $promotion,
            ),
        )
        ->toBeTrue()
        ->and($receipt->promotionRedemptions[0]->finalPrice->amount)
        ->toBe(270)
        ->and($receipt->subtotal->amount - $receipt->total->amount)
        ->toBe(30);
});

it(
    'skips lattice promotion creation when either budget type is exhausted',
    function (
        ?int $applicationBudget,
        ?int $monetaryBudget,
        int $originalPrice,
        int $finalPrice,
    ): void {
        $this->promotion->update([
            'application_budget' => $applicationBudget,
            'monetary_budget' => $monetaryBudget,
        ]);

        $stack = PromotionStack::factory()->for($this->team)->create();

        $product = \App\Models\Product::factory()
            ->for($this->team)
            ->create(['price' => 5_00]);

        $cart = Cart::factory()->for($this->team)->create();

        $cartItem = CartItem::factory()
            ->for($cart)
            ->for($product)
            ->create([
                'price' => 500,
                'price_currency' => 'GBP',
                'offer_price' => 500,
                'offer_price_currency' => 'GBP',
            ]);

        PromotionRedemption::query()->create([
            'promotion_id' => $this->promotion->id,
            'promotion_stack_id' => $stack->id,
            'redeemable_type' => $cartItem->getMorphClass(),
            'redeemable_id' => $cartItem->id,
            'sort_order' => 0,
            'original_price' => $originalPrice,
            'original_price_currency' => 'GBP',
            'final_price' => $finalPrice,
            'final_price_currency' => 'GBP',
        ]);

        $promotion = Promotion::query()
            ->withGraph()
            ->findOrFail($this->promotion->id);

        $latticePromotion = app(LatticePromotionFactory::class)->make(
            $promotion,
        );

        expect($latticePromotion)->toBeNull();
    },
)->with([
    'application budget exhausted' => [1, null, 500, 500],
    'monetary budget exhausted' => [null, 50, 500, 450],
]);
