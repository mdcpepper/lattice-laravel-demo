<?php

namespace Tests\Unit\Services;

use App\Enums\MixAndMatchDiscountKind;
use App\Enums\QualificationContext;
use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Enums\SimpleDiscountKind;
use App\Enums\TieredThresholdDiscountKind;
use App\Models\Product;
use App\Models\Promotions\DirectDiscountPromotion;
use App\Models\Promotions\MixAndMatchDiscount;
use App\Models\Promotions\MixAndMatchPromotion;
use App\Models\Promotions\PositionalDiscountPromotion;
use App\Models\Promotions\Promotion;
use App\Models\Promotions\SimpleDiscount;
use App\Models\Promotions\TieredThresholdDiscount;
use App\Models\Promotions\TieredThresholdPromotion;
use App\Models\Team;
use App\Services\ProductQualificationChecker;

beforeEach(function (): void {
    $this->team = Team::factory()->create();
});

describe('direct discount promotion', function (): void {
    beforeEach(function (): void {
        buildDirectDiscountPromotion('10% Off', $this->team);
        $this->checker = app(ProductQualificationChecker::class);
    });

    it('matches a product with eligible + vip tags', function (): void {
        $product = Product::factory()->for($this->team)->create();
        $product->syncTags(['eligible', 'vip']);

        expect($this->checker->qualifyingPromotionNames($product))->toBe([
            '10% Off',
        ]);
    });

    it(
        'matches a product with eligible tag but no blocked tag (HasNone passes)',
        function (): void {
            $product = Product::factory()->for($this->team)->create();
            $product->syncTags(['eligible']);

            expect($this->checker->qualifyingPromotionNames($product))->toBe([
                '10% Off',
            ]);
        },
    );

    it('does not match a product missing the eligible tag', function (): void {
        $product = Product::factory()->for($this->team)->create();
        $product->syncTags(['vip']);

        expect($this->checker->qualifyingPromotionNames($product))->toBeEmpty();
    });

    it(
        'does not match a product with eligible + blocked but no vip (group OR fails)',
        function (): void {
            $product = Product::factory()->for($this->team)->create();
            $product->syncTags(['eligible', 'blocked']);

            expect(
                $this->checker->qualifyingPromotionNames($product),
            )->toBeEmpty();
        },
    );

    it('does not match a product with no tags', function (): void {
        $product = Product::factory()->for($this->team)->create();

        expect($this->checker->qualifyingPromotionNames($product))->toBeEmpty();
    });
});

describe('positional discount promotion', function (): void {
    beforeEach(function (): void {
        buildPositionalDiscountPromotion('BOGOF Drinks', $this->team);
        $this->checker = app(ProductQualificationChecker::class);
    });

    it('matches a product with eligible + vip tags', function (): void {
        $product = Product::factory()->for($this->team)->create();
        $product->syncTags(['eligible', 'vip']);

        expect($this->checker->qualifyingPromotionNames($product))->toBe([
            'BOGOF Drinks',
        ]);
    });

    it(
        'matches a product with eligible tag but no blocked tag (HasNone passes)',
        function (): void {
            $product = Product::factory()->for($this->team)->create();
            $product->syncTags(['eligible']);

            expect($this->checker->qualifyingPromotionNames($product))->toBe([
                'BOGOF Drinks',
            ]);
        },
    );

    it('does not match a product missing the eligible tag', function (): void {
        $product = Product::factory()->for($this->team)->create();
        $product->syncTags(['vip']);

        expect($this->checker->qualifyingPromotionNames($product))->toBeEmpty();
    });

    it(
        'does not match a product with eligible + blocked but no vip (group OR fails)',
        function (): void {
            $product = Product::factory()->for($this->team)->create();
            $product->syncTags(['eligible', 'blocked']);

            expect(
                $this->checker->qualifyingPromotionNames($product),
            )->toBeEmpty();
        },
    );

    it('does not match a product with no tags', function (): void {
        $product = Product::factory()->for($this->team)->create();

        expect($this->checker->qualifyingPromotionNames($product))->toBeEmpty();
    });
});

describe('mix and match promotion', function (): void {
    beforeEach(function (): void {
        buildMixAndMatchPromotion('Buy 2 Get 1', $this->team);

        $this->checker = app(ProductQualificationChecker::class);
    });

    it('matches a product qualifying for slot A', function (): void {
        $product = Product::factory()->for($this->team)->create();
        $product->syncTags(['category-a']);

        expect($this->checker->qualifyingPromotionNames($product))->toBe([
            'Buy 2 Get 1',
        ]);
    });

    it('matches a product qualifying for slot B', function (): void {
        $product = Product::factory()->for($this->team)->create();
        $product->syncTags(['category-b']);

        expect($this->checker->qualifyingPromotionNames($product))->toBe([
            'Buy 2 Get 1',
        ]);
    });

    it('does not match a product that fits neither slot', function (): void {
        $product = Product::factory()->for($this->team)->create();
        $product->syncTags(['category-c']);

        expect($this->checker->qualifyingPromotionNames($product))->toBeEmpty();
    });
});

describe('tiered threshold promotion', function (): void {
    beforeEach(function (): void {
        buildTieredThresholdPromotion('Tiered Deal', $this->team);

        $this->checker = app(ProductQualificationChecker::class);
    });

    it('matches a product qualifying for a tier', function (): void {
        $product = Product::factory()->for($this->team)->create();
        $product->syncTags(['tiered:eligible']);

        expect($this->checker->qualifyingPromotionNames($product))->toBe([
            'Tiered Deal',
        ]);
    });

    it('does not match a product outside all tiers', function (): void {
        $product = Product::factory()->for($this->team)->create();
        $product->syncTags(['tiered:other']);

        expect($this->checker->qualifyingPromotionNames($product))->toBeEmpty();
    });
});

it(
    'returns multiple promotion names when a product qualifies for several',
    function (): void {
        buildDirectDiscountPromotion('10% Off', $this->team);
        buildMixAndMatchPromotion('Buy 2 Get 1', $this->team);

        $product = Product::factory()->for($this->team)->create();
        $product->syncTags(['eligible', 'category-a']);

        $checker = app(ProductQualificationChecker::class);
        $names = $checker->qualifyingPromotionNames($product);

        expect($names)
            ->toContain('10% Off')
            ->toContain('Buy 2 Get 1')
            ->toHaveCount(2);
    },
);

it('returns empty array when there are no promotions', function (): void {
    $product = Product::factory()->for($this->team)->create();
    $product->syncTags(['eligible', 'vip']);

    $checker = app(ProductQualificationChecker::class);

    expect($checker->qualifyingPromotionNames($product))->toBeEmpty();
});

// AND(has_all([eligible]), group(OR(has_any([vip]), has_none([blocked]))))
function buildDirectDiscountPromotion(
    string $name = '10% Off',
    ?Team $team = null,
): Promotion {
    $team ??= Team::factory()->create();
    $discount = SimpleDiscount::query()->create([
        'kind' => SimpleDiscountKind::PercentageOff,
        'percentage' => 1000,
    ]);

    $direct = DirectDiscountPromotion::query()->create([
        'simple_discount_id' => $discount->id,
    ]);

    $promotion = Promotion::query()->create([
        'name' => $name,
        'team_id' => $team->id,
        'promotionable_type' => $direct->getMorphClass(),
        'promotionable_id' => $direct->id,
    ]);

    $root = $direct->qualification()->create([
        'promotion_id' => $promotion->id,
        'context' => QualificationContext::Primary->value,
        'op' => QualificationOp::And,
        'sort_order' => 0,
    ]);

    $hasAllRule = $root->rules()->create([
        'kind' => QualificationRuleKind::HasAll,
        'sort_order' => 0,
    ]);

    $hasAllRule->syncTags(['eligible']);

    $group = $promotion->qualifications()->create([
        'parent_qualification_id' => $root->id,
        'context' => QualificationContext::Group->value,
        'op' => QualificationOp::Or,
        'sort_order' => 0,
    ]);

    $root->rules()->create([
        'kind' => QualificationRuleKind::Group,
        'group_qualification_id' => $group->id,
        'sort_order' => 1,
    ]);

    $hasAnyRule = $group->rules()->create([
        'kind' => QualificationRuleKind::HasAny,
        'sort_order' => 0,
    ]);

    $hasAnyRule->syncTags(['vip']);

    $hasNoneRule = $group->rules()->create([
        'kind' => QualificationRuleKind::HasNone,
        'sort_order' => 1,
    ]);

    $hasNoneRule->syncTags(['blocked']);

    return $promotion;
}

// AND(has_all([eligible]), group(OR(has_any([vip]), has_none([blocked]))))
function buildPositionalDiscountPromotion(
    string $name = 'BOGOF Drinks',
    ?Team $team = null,
): Promotion {
    $team ??= Team::factory()->create();
    $discount = SimpleDiscount::query()->create([
        'kind' => SimpleDiscountKind::PercentageOff,
        'percentage' => 1000,
    ]);

    $positional = PositionalDiscountPromotion::query()->create([
        'simple_discount_id' => $discount->id,
        'size' => 2,
    ]);

    $promotion = Promotion::query()->create([
        'name' => $name,
        'team_id' => $team->id,
        'promotionable_type' => $positional->getMorphClass(),
        'promotionable_id' => $positional->id,
    ]);

    $root = $positional->qualification()->create([
        'promotion_id' => $promotion->id,
        'context' => QualificationContext::Primary->value,
        'op' => QualificationOp::And,
        'sort_order' => 0,
    ]);

    $hasAllRule = $root->rules()->create([
        'kind' => QualificationRuleKind::HasAll,
        'sort_order' => 0,
    ]);

    $hasAllRule->syncTags(['eligible']);

    $group = $promotion->qualifications()->create([
        'parent_qualification_id' => $root->id,
        'context' => QualificationContext::Group->value,
        'op' => QualificationOp::Or,
        'sort_order' => 0,
    ]);

    $root->rules()->create([
        'kind' => QualificationRuleKind::Group,
        'group_qualification_id' => $group->id,
        'sort_order' => 1,
    ]);

    $hasAnyRule = $group->rules()->create([
        'kind' => QualificationRuleKind::HasAny,
        'sort_order' => 0,
    ]);

    $hasAnyRule->syncTags(['vip']);

    $hasNoneRule = $group->rules()->create([
        'kind' => QualificationRuleKind::HasNone,
        'sort_order' => 1,
    ]);

    $hasNoneRule->syncTags(['blocked']);

    return $promotion;
}

function buildMixAndMatchPromotion(
    string $name = 'Buy 2 Get 1',
    ?Team $team = null,
): Promotion {
    $team ??= Team::factory()->create();
    $discount = MixAndMatchDiscount::query()->create([
        'kind' => MixAndMatchDiscountKind::PercentageOffAllItems,
        'percentage' => 1000,
    ]);

    $mixAndMatch = MixAndMatchPromotion::query()->create([
        'mix_and_match_discount_id' => $discount->id,
    ]);

    $promotion = Promotion::query()->create([
        'name' => $name,
        'team_id' => $team->id,
        'promotionable_type' => $mixAndMatch->getMorphClass(),
        'promotionable_id' => $mixAndMatch->id,
    ]);

    $slotA = $mixAndMatch->slots()->create(['min' => 1, 'sort_order' => 0]);

    $slotAQual = $slotA->qualification()->create([
        'promotion_id' => $promotion->id,
        'context' => QualificationContext::Primary->value,
        'op' => QualificationOp::And,
        'sort_order' => 0,
    ]);

    $ruleA = $slotAQual
        ->rules()
        ->create(['kind' => QualificationRuleKind::HasAny, 'sort_order' => 0]);

    $ruleA->syncTags(['category-a']);

    $slotB = $mixAndMatch->slots()->create(['min' => 1, 'sort_order' => 1]);

    $slotBQual = $slotB->qualification()->create([
        'promotion_id' => $promotion->id,
        'context' => QualificationContext::Primary->value,
        'op' => QualificationOp::And,
        'sort_order' => 0,
    ]);

    $ruleB = $slotBQual
        ->rules()
        ->create(['kind' => QualificationRuleKind::HasAny, 'sort_order' => 0]);

    $ruleB->syncTags(['category-b']);

    return $promotion;
}

function buildTieredThresholdPromotion(
    string $name = 'Tiered Deal',
    ?Team $team = null,
): Promotion {
    $team ??= Team::factory()->create();
    $tieredThreshold = TieredThresholdPromotion::query()->create();

    $promotion = Promotion::query()->create([
        'name' => $name,
        'team_id' => $team->id,
        'promotionable_type' => $tieredThreshold->getMorphClass(),
        'promotionable_id' => $tieredThreshold->id,
    ]);

    $discount = TieredThresholdDiscount::query()->create([
        'kind' => TieredThresholdDiscountKind::AmountOffTotal,
        'amount' => 150,
        'amount_currency' => 'GBP',
    ]);

    $tier = $tieredThreshold->tiers()->create([
        'tiered_threshold_discount_id' => $discount->id,
        'sort_order' => 0,
        'lower_item_count_threshold' => 1,
    ]);

    $tierQualification = $tier->qualification()->create([
        'promotion_id' => $promotion->id,
        'context' => QualificationContext::Primary->value,
        'op' => QualificationOp::And,
        'sort_order' => 0,
    ]);

    $tierRule = $tierQualification->rules()->create([
        'kind' => QualificationRuleKind::HasAny,
        'sort_order' => 0,
    ]);

    $tierRule->syncTags(['tiered:eligible']);

    return $promotion;
}
