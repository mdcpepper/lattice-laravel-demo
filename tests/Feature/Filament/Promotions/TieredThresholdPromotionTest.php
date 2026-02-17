<?php

namespace Tests\Feature\Filament\Promotions;

use App\Enums\PromotionType;
use App\Enums\QualificationContext;
use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Enums\TieredThresholdDiscountKind;
use App\Filament\Admin\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Admin\Resources\Promotions\Pages\EditPromotion;
use App\Models\Promotion;
use App\Models\Team;
use App\Models\TieredThresholdDiscount;
use App\Models\TieredThresholdPromotion;
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

it('can create a tiered threshold promotion', function (): void {
    Livewire::test(CreatePromotion::class)
        ->fillForm([
            'name' => 'Tiered Promo',
            'promotion_type' => PromotionType::TieredThreshold->value,
            'application_budget' => 25,
            'monetary_budget' => '100.00',
            'tiers' => [
                [
                    'lower_item_count_threshold' => 1,
                    'upper_item_count_threshold' => 3,
                    'discount_kind' => TieredThresholdDiscountKind::PercentageOffEachItem
                        ->value,
                    'discount_percentage' => 12.5,
                    'qualification_op' => QualificationOp::And->value,
                    'qualification_rules' => [
                        [
                            'kind' => QualificationRuleKind::HasAll->value,
                            'tags' => ['tiered:eligible'],
                            'group_op' => null,
                            'group_rules' => [],
                        ],
                    ],
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $promotion = Promotion::query()
        ->where('name', 'Tiered Promo')
        ->firstOrFail();

    $promotionable = $promotion->promotionable;

    expect($promotionable)->toBeInstanceOf(TieredThresholdPromotion::class);

    $tier = $promotionable->tiers()->first();

    expect($tier)
        ->not->toBeNull()
        ->and($tier->lower_item_count_threshold)
        ->toBe(1)
        ->and($tier->upper_item_count_threshold)
        ->toBe(3);

    $this->assertDatabaseHas('tiered_threshold_discounts', [
        'id' => $tier->tiered_threshold_discount_id,
        'kind' => TieredThresholdDiscountKind::PercentageOffEachItem->value,
        'percentage' => 1250,
    ]);

    $qualification = $tier->qualification()->first();
    $rule = $qualification->rules()->first();

    expect($rule->tags->pluck('name')->all())->toBe(['tiered:eligible']);
});

it(
    'validates discount amount when tier kind is amount-based',
    function (): void {
        Livewire::test(CreatePromotion::class)
            ->fillForm([
                'name' => 'Tiered Amount',
                'promotion_type' => PromotionType::TieredThreshold->value,
                'tiers' => [
                    [
                        'lower_item_count_threshold' => 1,
                        'discount_kind' => TieredThresholdDiscountKind::AmountOffTotal->value,
                        'discount_amount' => null,
                        'qualification_op' => QualificationOp::And->value,
                        'qualification_rules' => [],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['tiers.0.discount_amount']);
    },
);

it(
    'loads an existing tiered threshold promotion into the edit form',
    function (): void {
        $discount = TieredThresholdDiscount::query()->create([
            'kind' => TieredThresholdDiscountKind::AmountOffTotal,
            'amount' => 550,
            'amount_currency' => 'GBP',
        ]);

        $tiered = TieredThresholdPromotion::query()->create();

        $promotion = Promotion::query()->create([
            'name' => 'Edit Tiered',
            'application_budget' => 10,
            'monetary_budget' => 2000,
            'team_id' => $this->team->id,
            'promotionable_type' => $tiered->getMorphClass(),
            'promotionable_id' => $tiered->id,
        ]);

        $tier = $tiered->tiers()->create([
            'tiered_threshold_discount_id' => $discount->id,
            'sort_order' => 0,
            'lower_monetary_threshold_minor' => 250,
            'lower_monetary_threshold_currency' => 'GBP',
            'upper_item_count_threshold' => 4,
        ]);

        $tier->qualification()->create([
            'promotion_id' => $promotion->id,
            'context' => QualificationContext::Primary->value,
            'op' => QualificationOp::Or,
            'sort_order' => 0,
        ]);

        $component = Livewire::test(EditPromotion::class, [
            'record' => $promotion->getRouteKey(),
        ])->assertSet(
            'data.promotion_type',
            PromotionType::TieredThreshold->value,
        );

        $tiers = array_values($component->get('data.tiers'));

        expect($tiers)
            ->toHaveCount(1)
            ->and($tiers[0]['lower_monetary_threshold'])
            ->toBe(2.5)
            ->and($tiers[0]['upper_item_count_threshold'])
            ->toBe(4.0)
            ->and($tiers[0]['discount_kind'])
            ->toBe(TieredThresholdDiscountKind::AmountOffTotal->value)
            ->and($tiers[0]['discount_amount'])
            ->toBe(5.5)
            ->and($tiers[0]['qualification_op'])
            ->toBe(QualificationOp::Or->value);
    },
);

it(
    'can update tiered threshold tiers and replace old discounts',
    function (): void {
        $oldDiscount = TieredThresholdDiscount::query()->create([
            'kind' => TieredThresholdDiscountKind::AmountOffTotal,
            'amount' => 300,
            'amount_currency' => 'GBP',
        ]);

        $tiered = TieredThresholdPromotion::query()->create();

        $promotion = Promotion::query()->create([
            'name' => 'Initial Tiered',
            'team_id' => $this->team->id,
            'promotionable_type' => $tiered->getMorphClass(),
            'promotionable_id' => $tiered->id,
        ]);

        $tier = $tiered->tiers()->create([
            'tiered_threshold_discount_id' => $oldDiscount->id,
            'sort_order' => 0,
            'lower_item_count_threshold' => 1,
        ]);

        $root = $tier->qualification()->create([
            'promotion_id' => $promotion->id,
            'context' => QualificationContext::Primary->value,
            'op' => QualificationOp::And,
            'sort_order' => 0,
        ]);

        $oldRule = $root->rules()->create([
            'kind' => QualificationRuleKind::HasAll,
            'sort_order' => 0,
        ]);
        $oldRule->syncTags(['old-tier-tag']);

        Livewire::test(EditPromotion::class, [
            'record' => $promotion->getRouteKey(),
        ])
            ->fillForm([
                'name' => 'Updated Tiered',
                'promotion_type' => PromotionType::TieredThreshold->value,
                'application_budget' => 40,
                'monetary_budget' => '80.00',
            ])
            ->set('data.tiers', [
                [
                    'lower_item_count_threshold' => 1,
                    'upper_item_count_threshold' => 2,
                    'discount_kind' => TieredThresholdDiscountKind::PercentageOffEachItem
                        ->value,
                    'discount_percentage' => 10.0,
                    'qualification_op' => QualificationOp::And->value,
                    'qualification_rules' => [
                        [
                            'kind' => QualificationRuleKind::HasAny->value,
                            'tags' => ['new-tier-tag'],
                            'group_op' => null,
                            'group_rules' => [],
                        ],
                    ],
                ],
                [
                    'lower_monetary_threshold' => '10.00',
                    'discount_kind' => TieredThresholdDiscountKind::AmountOffTotal->value,
                    'discount_amount' => '2.50',
                    'qualification_op' => QualificationOp::And->value,
                    'qualification_rules' => [],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $promotion->refresh();
        $promotionable = $promotion->promotionable;

        expect($promotionable->tiers()->count())->toBe(2);

        $this->assertDatabaseMissing('tiered_threshold_discounts', [
            'id' => $oldDiscount->id,
        ]);

        $this->assertDatabaseMissing('qualification_rules', [
            'id' => $oldRule->id,
        ]);

        $firstTier = $promotionable->tiers()->orderBy('sort_order')->first();
        $firstRule = $firstTier->qualification()->first()->rules()->first();

        expect($firstRule->tags->pluck('name')->all())->toBe(['new-tier-tag']);
    },
);
