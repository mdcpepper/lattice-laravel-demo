<?php

namespace Tests\Feature\Filament;

use App\Enums\SimpleDiscountKind;
use App\Filament\Admin\Resources\Promotions\Pages\ListPromotions;
use App\Models\DirectDiscountPromotion;
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
