<?php

namespace Tests\Feature\Filament;

use App\Enums\MixAndMatchDiscountKind;
use App\Enums\SimpleDiscountKind;
use App\Filament\Admin\Resources\Promotions\Pages\ListPromotions;
use App\Models\DirectDiscountPromotion;
use App\Models\MixAndMatchDiscount;
use App\Models\MixAndMatchPromotion;
use App\Models\Promotion;
use App\Models\SimpleDiscount;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
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

    Livewire::test(ListPromotions::class)
        ->assertSee('Percentage Off: 10%')
        ->assertSee('Amount Off Total: £5.00');
});
