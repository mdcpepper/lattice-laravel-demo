<?php

namespace Tests\Feature\Filament\Promotions;

use App\Enums\MixAndMatchDiscountKind;
use App\Enums\QualificationContext;
use App\Enums\QualificationOp;
use App\Enums\SimpleDiscountKind;
use App\Enums\TieredThresholdDiscountKind;
use App\Filament\Admin\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Admin\Resources\Promotions\Pages\ListPromotions;
use App\Models\Cart\CartItem;
use App\Models\Promotions\DirectDiscountPromotion;
use App\Models\Promotions\MixAndMatchDiscount;
use App\Models\Promotions\MixAndMatchPromotion;
use App\Models\Promotions\Promotion;
use App\Models\Promotions\PromotionRedemption;
use App\Models\Promotions\PromotionStack;
use App\Models\Promotions\SimpleDiscount;
use App\Models\Promotions\TieredThresholdDiscount;
use App\Models\Promotions\TieredThresholdPromotion;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $user = User::factory()->create();
    $this->team = Team::factory()->create();

    $this->team->members()->attach($user);

    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->team, isQuiet: true);

    $this->actingAs($user);
});

it('can render the list page', function (): void {
    Livewire::test(ListPromotions::class)->assertSuccessful();
});

it('can see promotions in the table', function (): void {
    $discount = SimpleDiscount::query()->create([
        'kind' => SimpleDiscountKind::PercentageOff,
        'percentage' => 10.0,
    ]);

    $direct = DirectDiscountPromotion::query()->create([
        'simple_discount_id' => $discount->id,
    ]);

    $promotion = Promotion::query()->create([
        'name' => 'Visible Promo',
        'team_id' => $this->team->id,
        'promotionable_type' => $direct->getMorphClass(),
        'promotionable_id' => $direct->id,
    ]);

    Livewire::test(ListPromotions::class)->assertCanSeeTableRecords([
        $promotion,
    ]);
});

it('shows formatted discount configuration in the table', function (): void {
    $directDiscount = SimpleDiscount::query()->create([
        'kind' => SimpleDiscountKind::PercentageOff,
        'percentage' => 10.0,
    ]);

    $directPromotionable = DirectDiscountPromotion::query()->create([
        'simple_discount_id' => $directDiscount->id,
    ]);

    Promotion::query()->create([
        'name' => 'Direct Promo',
        'team_id' => $this->team->id,
        'promotionable_type' => $directPromotionable->getMorphClass(),
        'promotionable_id' => $directPromotionable->id,
    ]);

    $mixDiscount = MixAndMatchDiscount::query()->create([
        'kind' => MixAndMatchDiscountKind::AmountOffTotal,
        'amount' => 500,
        'amount_currency' => 'GBP',
    ]);

    $mixPromotionable = MixAndMatchPromotion::query()->create([
        'mix_and_match_discount_id' => $mixDiscount->id,
    ]);

    Promotion::query()->create([
        'name' => 'Mix Promo',
        'team_id' => $this->team->id,
        'promotionable_type' => $mixPromotionable->getMorphClass(),
        'promotionable_id' => $mixPromotionable->id,
    ]);

    $tieredPromotionable = TieredThresholdPromotion::query()->create();
    $tieredDiscount = TieredThresholdDiscount::query()->create([
        'kind' => TieredThresholdDiscountKind::AmountOffTotal,
        'amount' => 300,
        'amount_currency' => 'GBP',
    ]);

    $tieredPromotionable->tiers()->create([
        'tiered_threshold_discount_id' => $tieredDiscount->id,
        'sort_order' => 0,
        'lower_item_count_threshold' => 1,
    ]);

    Promotion::query()->create([
        'name' => 'Tiered Promo',
        'team_id' => $this->team->id,
        'promotionable_type' => $tieredPromotionable->getMorphClass(),
        'promotionable_id' => $tieredPromotionable->id,
    ]);

    Livewire::test(ListPromotions::class)
        ->assertSee('Percentage Off: 10%')
        ->assertSee('Amount Off Total: £5.00')
        ->assertSee('Tiered Threshold: 1 tier');
});

it(
    'shows budget usage as redeemed over budget in the application budget column',
    function (): void {
        $discount = SimpleDiscount::query()->create([
            'kind' => SimpleDiscountKind::PercentageOff,
            'percentage' => 10.0,
        ]);

        $direct = DirectDiscountPromotion::query()->create([
            'simple_discount_id' => $discount->id,
        ]);

        $promotion = Promotion::query()->create([
            'name' => 'Budget Promo',
            'team_id' => $this->team->id,
            'promotionable_type' => $direct->getMorphClass(),
            'promotionable_id' => $direct->id,
            'application_budget' => 10,
        ]);

        $stack = PromotionStack::factory()->for($this->team)->create();

        foreach (range(1, 3) as $_) {
            PromotionRedemption::query()->create([
                'promotion_id' => $promotion->id,
                'promotion_stack_id' => $stack->id,
                'redeemable_type' => CartItem::getMorphString(),
                'original_price' => 500,
                'original_price_currency' => 'GBP',
                'final_price' => 450,
                'final_price_currency' => 'GBP',
            ]);
        }

        Livewire::test(ListPromotions::class)->assertSee('3 / 10');
    },
);

it(
    'shows infinity symbol when there is no application budget',
    function (): void {
        $discount = SimpleDiscount::query()->create([
            'kind' => SimpleDiscountKind::PercentageOff,
            'percentage' => 10.0,
        ]);

        $direct = DirectDiscountPromotion::query()->create([
            'simple_discount_id' => $discount->id,
        ]);

        $promotion = Promotion::query()->create([
            'name' => 'Unlimited Promo',
            'team_id' => $this->team->id,
            'promotionable_type' => $direct->getMorphClass(),
            'promotionable_id' => $direct->id,
        ]);

        $stack = PromotionStack::factory()->for($this->team)->create();

        foreach (range(1, 2) as $_) {
            PromotionRedemption::query()->create([
                'promotion_id' => $promotion->id,
                'promotion_stack_id' => $stack->id,
                'redeemable_type' => CartItem::getMorphString(),
                'original_price' => 500,
                'original_price_currency' => 'GBP',
                'final_price' => 450,
                'final_price_currency' => 'GBP',
            ]);
        }

        Livewire::test(ListPromotions::class)->assertSee('2 / ∞');
    },
);

it(
    'shows monetary redeemed over budget in the monetary budget column',
    function (): void {
        $discount = SimpleDiscount::query()->create([
            'kind' => SimpleDiscountKind::PercentageOff,
            'percentage' => 10.0,
        ]);

        $direct = DirectDiscountPromotion::query()->create([
            'simple_discount_id' => $discount->id,
        ]);

        // monetary_budget = £5.00 (stored as 500 pence)
        $promotion = Promotion::query()->create([
            'name' => 'Money Promo',
            'team_id' => $this->team->id,
            'promotionable_type' => $direct->getMorphClass(),
            'promotionable_id' => $direct->id,
            'monetary_budget' => 500,
        ]);

        $stack = PromotionStack::factory()->for($this->team)->create();

        // 3 redemptions × (500 - 450) = 150 pence = £1.50 redeemed
        foreach (range(1, 3) as $_) {
            PromotionRedemption::query()->create([
                'promotion_id' => $promotion->id,
                'promotion_stack_id' => $stack->id,
                'redeemable_type' => CartItem::getMorphString(),
                'original_price' => 500,
                'original_price_currency' => 'GBP',
                'final_price' => 450,
                'final_price_currency' => 'GBP',
            ]);
        }

        Livewire::test(ListPromotions::class)->assertSee('£1.50 / £5.00');
    },
);

it('shows infinity symbol when there is no monetary budget', function (): void {
    $discount = SimpleDiscount::query()->create([
        'kind' => SimpleDiscountKind::PercentageOff,
        'percentage' => 10.0,
    ]);

    $direct = DirectDiscountPromotion::query()->create([
        'simple_discount_id' => $discount->id,
    ]);

    $promotion = Promotion::query()->create([
        'name' => 'Unlimited Money Promo',
        'team_id' => $this->team->id,
        'promotionable_type' => $direct->getMorphClass(),
        'promotionable_id' => $direct->id,
    ]);

    $stack = PromotionStack::factory()->for($this->team)->create();

    // 3 redemptions × 50p = £1.50 redeemed
    foreach (range(1, 3) as $_) {
        PromotionRedemption::query()->create([
            'promotion_id' => $promotion->id,
            'promotion_stack_id' => $stack->id,
            'redeemable_type' => CartItem::getMorphString(),
            'original_price' => 500,
            'original_price_currency' => 'GBP',
            'final_price' => 450,
            'final_price_currency' => 'GBP',
        ]);
    }

    Livewire::test(ListPromotions::class)->assertSee('£1.50 / ∞');
});

it(
    'cannot set application budget below already redeemed count',
    function (): void {
        $discount = SimpleDiscount::query()->create([
            'kind' => SimpleDiscountKind::PercentageOff,
            'percentage' => 10.0,
        ]);

        $direct = DirectDiscountPromotion::query()->create([
            'simple_discount_id' => $discount->id,
        ]);

        $promotion = Promotion::query()->create([
            'name' => 'Capped Promo',
            'team_id' => $this->team->id,
            'promotionable_type' => $direct->getMorphClass(),
            'promotionable_id' => $direct->id,
            'application_budget' => 10,
        ]);

        $direct->qualification()->create([
            'promotion_id' => $promotion->id,
            'context' => QualificationContext::Primary->value,
            'op' => QualificationOp::And,
            'sort_order' => 0,
        ]);

        $stack = PromotionStack::factory()->for($this->team)->create();

        foreach (range(1, 5) as $_) {
            PromotionRedemption::query()->create([
                'promotion_id' => $promotion->id,
                'promotion_stack_id' => $stack->id,
                'redeemable_type' => CartItem::getMorphString(),
                'original_price' => 500,
                'original_price_currency' => 'GBP',
                'final_price' => 450,
                'final_price_currency' => 'GBP',
            ]);
        }

        Livewire::test(EditPromotion::class, [
            'record' => $promotion->getRouteKey(),
        ])
            ->fillForm([
                'name' => 'Capped Promo',
                'promotion_type' => 'direct_discount',
                'application_budget' => 3,
                'discount_kind' => SimpleDiscountKind::PercentageOff->value,
                'discount_percentage' => 10.0,
                'qualification_op' => QualificationOp::And->value,
                'qualification_rules' => [],
            ])
            ->call('save')
            ->assertHasFormErrors(['application_budget' => 'min']);
    },
);

it(
    'cannot set monetary budget below already redeemed amount',
    function (): void {
        $discount = SimpleDiscount::query()->create([
            'kind' => SimpleDiscountKind::PercentageOff,
            'percentage' => 10.0,
        ]);

        $direct = DirectDiscountPromotion::query()->create([
            'simple_discount_id' => $discount->id,
        ]);

        // monetary_budget = £10.00 (1000 pence)
        $promotion = Promotion::query()->create([
            'name' => 'Money Cap Promo',
            'team_id' => $this->team->id,
            'promotionable_type' => $direct->getMorphClass(),
            'promotionable_id' => $direct->id,
            'monetary_budget' => 1000,
        ]);

        $direct->qualification()->create([
            'promotion_id' => $promotion->id,
            'context' => QualificationContext::Primary->value,
            'op' => QualificationOp::And,
            'sort_order' => 0,
        ]);

        $stack = PromotionStack::factory()->for($this->team)->create();

        // 4 redemptions × 50p = 200p = £2.00 redeemed
        foreach (range(1, 4) as $_) {
            PromotionRedemption::query()->create([
                'promotion_id' => $promotion->id,
                'promotion_stack_id' => $stack->id,
                'redeemable_type' => CartItem::getMorphString(),
                'original_price' => 500,
                'original_price_currency' => 'GBP',
                'final_price' => 450,
                'final_price_currency' => 'GBP',
            ]);
        }

        Livewire::test(EditPromotion::class, [
            'record' => $promotion->getRouteKey(),
        ])
            ->fillForm([
                'name' => 'Money Cap Promo',
                'promotion_type' => 'direct_discount',
                'monetary_budget' => '1.00',
                'discount_kind' => SimpleDiscountKind::PercentageOff->value,
                'discount_percentage' => 10.0,
                'qualification_op' => QualificationOp::And->value,
                'qualification_rules' => [],
            ])
            ->call('save')
            ->assertHasFormErrors(['monetary_budget' => 'min']);
    },
);
