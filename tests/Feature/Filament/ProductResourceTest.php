<?php

namespace Tests\Feature\Filament;

use App\Enums\QualificationContext;
use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Enums\SimpleDiscountKind;
use App\Filament\Admin\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\Promotions\DirectDiscountPromotion;
use App\Models\Promotions\Promotion;
use App\Models\Promotions\SimpleDiscount;
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

it('can filter products by a qualifying promotion', function (): void {
    $eligiblePromotion = buildHasAllDirectPromotion(
        team: $this->team,
        name: 'Eligible Promo',
        tags: ['eligible'],
    );

    buildHasAllDirectPromotion(
        team: $this->team,
        name: 'Seasonal Promo',
        tags: ['seasonal'],
    );

    $eligibleProduct = Product::factory()
        ->for($this->team)
        ->create(['name' => 'Eligible Product']);
    $eligibleProduct->syncTags(['eligible']);

    $seasonalProduct = Product::factory()
        ->for($this->team)
        ->create(['name' => 'Seasonal Product']);
    $seasonalProduct->syncTags(['seasonal']);

    $neutralProduct = Product::factory()
        ->for($this->team)
        ->create(['name' => 'Neutral Product']);
    $neutralProduct->syncTags(['neutral']);

    Livewire::test(ListProducts::class)
        ->filterTable('qualifying_promotions', [$eligiblePromotion->id])
        ->assertCanSeeTableRecords([$eligibleProduct])
        ->assertCanNotSeeTableRecords([$seasonalProduct, $neutralProduct]);
});

it('can filter products by multiple qualifying promotions', function (): void {
    $eligiblePromotion = buildHasAllDirectPromotion(
        team: $this->team,
        name: 'Eligible Promo',
        tags: ['eligible'],
    );

    $seasonalPromotion = buildHasAllDirectPromotion(
        team: $this->team,
        name: 'Seasonal Promo',
        tags: ['seasonal'],
    );

    $eligibleProduct = Product::factory()
        ->for($this->team)
        ->create(['name' => 'Eligible Product']);
    $eligibleProduct->syncTags(['eligible']);

    $seasonalProduct = Product::factory()
        ->for($this->team)
        ->create(['name' => 'Seasonal Product']);
    $seasonalProduct->syncTags(['seasonal']);

    $neutralProduct = Product::factory()
        ->for($this->team)
        ->create(['name' => 'Neutral Product']);
    $neutralProduct->syncTags(['neutral']);

    Livewire::test(ListProducts::class)
        ->filterTable('qualifying_promotions', [
            $eligiblePromotion->id,
            $seasonalPromotion->id,
        ])
        ->assertCanSeeTableRecords([$eligibleProduct, $seasonalProduct])
        ->assertCanNotSeeTableRecords([$neutralProduct]);
});

it('can filter products with no qualifying promotions', function (): void {
    buildHasAllDirectPromotion(
        team: $this->team,
        name: 'Eligible Promo',
        tags: ['eligible'],
    );

    $eligibleProduct = Product::factory()
        ->for($this->team)
        ->create(['name' => 'Eligible Product']);
    $eligibleProduct->syncTags(['eligible']);

    $neutralProduct = Product::factory()
        ->for($this->team)
        ->create(['name' => 'Neutral Product']);
    $neutralProduct->syncTags(['neutral']);

    Livewire::test(ListProducts::class)
        ->filterTable('qualifying_promotions', ['__none__'])
        ->assertCanSeeTableRecords([$neutralProduct])
        ->assertCanNotSeeTableRecords([$eligibleProduct]);
});

it(
    'can combine none with specific promotions in the filter',
    function (): void {
        $eligiblePromotion = buildHasAllDirectPromotion(
            team: $this->team,
            name: 'Eligible Promo',
            tags: ['eligible'],
        );

        buildHasAllDirectPromotion(
            team: $this->team,
            name: 'Seasonal Promo',
            tags: ['seasonal'],
        );

        $eligibleProduct = Product::factory()
            ->for($this->team)
            ->create(['name' => 'Eligible Product']);
        $eligibleProduct->syncTags(['eligible']);

        $seasonalProduct = Product::factory()
            ->for($this->team)
            ->create(['name' => 'Seasonal Product']);
        $seasonalProduct->syncTags(['seasonal']);

        $neutralProduct = Product::factory()
            ->for($this->team)
            ->create(['name' => 'Neutral Product']);
        $neutralProduct->syncTags(['neutral']);

        Livewire::test(ListProducts::class)
            ->filterTable('qualifying_promotions', [
                '__none__',
                $eligiblePromotion->id,
            ])
            ->assertCanSeeTableRecords([$eligibleProduct, $neutralProduct])
            ->assertCanNotSeeTableRecords([$seasonalProduct]);
    },
);

function buildHasAllDirectPromotion(
    Team $team,
    string $name,
    array $tags,
): Promotion {
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

    $qualification = $direct->qualification()->create([
        'promotion_id' => $promotion->id,
        'context' => QualificationContext::Primary->value,
        'op' => QualificationOp::And,
        'sort_order' => 0,
    ]);

    $rule = $qualification->rules()->create([
        'kind' => QualificationRuleKind::HasAll,
        'sort_order' => 0,
    ]);

    $rule->syncTags($tags);

    return $promotion;
}
