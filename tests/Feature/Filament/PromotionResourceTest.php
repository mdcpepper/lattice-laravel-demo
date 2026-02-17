<?php

namespace Tests\Feature\Filament;

use App\Enums\MixAndMatchDiscountKind;
use App\Enums\SimpleDiscountKind;
use App\Enums\TieredThresholdDiscountKind;
use App\Filament\Admin\Resources\Promotions\Pages\ListPromotions;
use App\Models\DirectDiscountPromotion;
use App\Models\MixAndMatchDiscount;
use App\Models\MixAndMatchPromotion;
use App\Models\Promotion;
use App\Models\SimpleDiscount;
use App\Models\Team;
use App\Models\TieredThresholdDiscount;
use App\Models\TieredThresholdPromotion;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user);

    Filament::setCurrentPanel('admin');
    Filament::setTenant($team, isQuiet: true);

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
        'promotionable_type' => $tieredPromotionable->getMorphClass(),
        'promotionable_id' => $tieredPromotionable->id,
    ]);

    Livewire::test(ListPromotions::class)
        ->assertSee('Percentage Off: 10%')
        ->assertSee('Amount Off Total: £5.00')
        ->assertSee('Tiered Threshold: 1 tier');
});
