<?php

namespace Tests\Feature\Filament\Promotions;

use App\Enums\MixAndMatchDiscountKind;
use App\Enums\PromotionType;
use App\Enums\QualificationContext;
use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Filament\Admin\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Admin\Resources\Promotions\Pages\EditPromotion;
use App\Models\MixAndMatchDiscount;
use App\Models\MixAndMatchPromotion;
use App\Models\Promotion;
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

it(
    'can create a mix and match promotion with percentage off',
    function (): void {
        Livewire::test(CreatePromotion::class)
            ->fillForm([
                'name' => '10% Off Meal Deal',
                'promotion_type' => PromotionType::MixAndMatch->value,
                'application_budget' => null,
                'monetary_budget' => null,
                'discount_kind' => MixAndMatchDiscountKind::PercentageOffAllItems->value,
                'discount_percentage' => 10.0,
                'slots' => [
                    [
                        'min' => 1,
                        'max' => 1,
                        'qualification_op' => QualificationOp::And->value,
                        'qualification_rules' => [
                            [
                                'kind' => QualificationRuleKind::HasAll->value,
                                'tags' => ['meal-deal:main'],
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
            ->where('name', '10% Off Meal Deal')
            ->firstOrFail();

        expect($promotion->application_budget)
            ->toBeNull()
            ->and($promotion->getRawOriginal('monetary_budget'))
            ->toBeNull();

        $mixAndMatch = $promotion->promotionable;

        expect($mixAndMatch)->toBeInstanceOf(MixAndMatchPromotion::class);

        $this->assertDatabaseHas('mix_and_match_discounts', [
            'id' => $mixAndMatch->mix_and_match_discount_id,
            'kind' => MixAndMatchDiscountKind::PercentageOffAllItems->value,
            'percentage' => 1000,
        ]);

        $this->assertDatabaseHas('mix_and_match_slots', [
            'mix_and_match_promotion_id' => $mixAndMatch->id,
            'min' => 1,
            'max' => 1,
            'sort_order' => 0,
        ]);

        $root = $mixAndMatch->slots()->first()->qualification()->first();
        $rule = $root->rules()->first();

        expect($rule->tags->pluck('name')->sort()->values()->all())->toBe([
            'meal-deal:main',
        ]);
    },
);

it('can create a promotion with amount off discount', function (): void {
    Livewire::test(CreatePromotion::class)
        ->fillForm([
            'name' => '£5 Off Meal Deal',
            'promotion_type' => PromotionType::MixAndMatch->value,
            'discount_kind' => MixAndMatchDiscountKind::AmountOffTotal->value,
            'discount_amount' => '5.00',
            'slots' => [
                [
                    'min' => 1,
                    'max' => 1,
                    'qualification_op' => QualificationOp::And->value,
                    'qualification_rules' => [],
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $promotion = Promotion::query()
        ->where('name', '£5 Off Meal Deal')
        ->firstOrFail();
    $mixAndMatch = $promotion->promotionable;

    $this->assertDatabaseHas('mix_and_match_discounts', [
        'id' => $mixAndMatch->mix_and_match_discount_id,
        'kind' => MixAndMatchDiscountKind::AmountOffTotal->value,
        'amount' => 500,
        'amount_currency' => 'GBP',
    ]);
});

it(
    'can create a promotion with a nested group qualification rule',
    function (): void {
        Livewire::test(CreatePromotion::class)
            ->fillForm([
                'name' => 'Group Meal Deal',
                'promotion_type' => PromotionType::MixAndMatch->value,
                'discount_kind' => MixAndMatchDiscountKind::PercentageOffAllItems->value,
                'discount_percentage' => 5.0,
                'slots' => [
                    [
                        'min' => 1,
                        'max' => 2,
                        'qualification_op' => QualificationOp::And->value,
                        'qualification_rules' => [
                            [
                                'kind' => QualificationRuleKind::Group->value,
                                'tags' => [],
                                'group_op' => QualificationOp::Or->value,
                                'group_rules' => [
                                    [
                                        'kind' => QualificationRuleKind::HasAny
                                            ->value,
                                        'tags' => ['vip', 'staff'],
                                    ],
                                    [
                                        'kind' => QualificationRuleKind::HasNone
                                            ->value,
                                        'tags' => ['blocked'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $promotion = Promotion::query()
            ->where('name', 'Group Meal Deal')
            ->firstOrFail();

        expect(Promotion::query()->count())->toBe(1);

        $qualifications = $promotion->qualifications;

        expect($qualifications)->toHaveCount(2);

        $groupQual = $qualifications->firstWhere(
            'context',
            QualificationContext::Group->value,
        );

        expect($groupQual)
            ->not->toBeNull()
            ->and($groupQual->op)
            ->toBe(QualificationOp::Or);

        expect($groupQual->rules()->count())->toBe(2);
    },
);

it('validates required fields on create', function (): void {
    Livewire::test(CreatePromotion::class)
        ->fillForm([
            'name' => null,
            'promotion_type' => PromotionType::MixAndMatch->value,
            'discount_kind' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'discount_kind' => 'required',
        ]);
});

it(
    'validates discount_percentage required when kind is percentage_off',
    function (): void {
        Livewire::test(CreatePromotion::class)
            ->fillForm([
                'name' => 'Test',
                'promotion_type' => PromotionType::MixAndMatch->value,
                'discount_kind' => MixAndMatchDiscountKind::PercentageOffAllItems->value,
                'discount_percentage' => null,
                'slots' => [
                    [
                        'min' => 1,
                        'max' => 1,
                        'qualification_op' => QualificationOp::And->value,
                        'qualification_rules' => [],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['discount_percentage']);
    },
);

it('can load an existing promotion into the edit form', function (): void {
    $discount = MixAndMatchDiscount::query()->create([
        'kind' => MixAndMatchDiscountKind::PercentageOffAllItems,
        'percentage' => 15.0,
    ]);

    $mixAndMatch = MixAndMatchPromotion::query()->create([
        'mix_and_match_discount_id' => $discount->id,
    ]);

    $promotion = Promotion::query()->create([
        'name' => 'Edit Me',
        'application_budget' => 50,
        'monetary_budget' => 10000,
        'team_id' => $this->team->id,
        'promotionable_type' => $mixAndMatch->getMorphClass(),
        'promotionable_id' => $mixAndMatch->id,
    ]);

    $slot = $mixAndMatch->slots()->create([
        'min' => 1,
        'max' => 2,
        'sort_order' => 0,
    ]);

    $slot->qualification()->create([
        'promotion_id' => $promotion->id,
        'context' => QualificationContext::Primary->value,
        'op' => QualificationOp::And,
        'sort_order' => 0,
    ]);

    $component = Livewire::test(EditPromotion::class, [
        'record' => $promotion->getRouteKey(),
    ])
        ->assertSet('data.name', 'Edit Me')
        ->assertSet('data.promotion_type', PromotionType::MixAndMatch->value)
        ->assertSet('data.application_budget', 50)
        ->assertSet('data.monetary_budget', '100.00')
        ->assertSet(
            'data.discount_kind',
            MixAndMatchDiscountKind::PercentageOffAllItems->value,
        )
        ->assertSet('data.discount_percentage', 15.0);

    $slots = array_values($component->get('data.slots'));

    expect($slots)
        ->toHaveCount(1)
        ->and($slots[0]['min'])
        ->toEqual(1)
        ->and($slots[0]['max'])
        ->toEqual(2)
        ->and($slots[0]['qualification_op'])
        ->toBe(QualificationOp::And->value);
});

it('can update a promotion name and budget', function (): void {
    $discount = MixAndMatchDiscount::query()->create([
        'kind' => MixAndMatchDiscountKind::PercentageOffAllItems,
        'percentage' => 10.0,
    ]);

    $mixAndMatch = MixAndMatchPromotion::query()->create([
        'mix_and_match_discount_id' => $discount->id,
    ]);

    $promotion = Promotion::query()->create([
        'name' => 'Original Name',
        'team_id' => $this->team->id,
        'promotionable_type' => $mixAndMatch->getMorphClass(),
        'promotionable_id' => $mixAndMatch->id,
    ]);

    $slot = $mixAndMatch->slots()->create([
        'min' => 1,
        'max' => 1,
        'sort_order' => 0,
    ]);

    $slot->qualification()->create([
        'promotion_id' => $promotion->id,
        'context' => QualificationContext::Primary->value,
        'op' => QualificationOp::And,
        'sort_order' => 0,
    ]);

    Livewire::test(EditPromotion::class, [
        'record' => $promotion->getRouteKey(),
    ])
        ->fillForm([
            'name' => 'Updated Name',
            'promotion_type' => PromotionType::MixAndMatch->value,
            'application_budget' => 25,
            'monetary_budget' => '50.00',
            'discount_kind' => MixAndMatchDiscountKind::PercentageOffAllItems->value,
            'discount_percentage' => 10.0,
        ])
        ->set('data.slots', [
            [
                'min' => 1,
                'max' => 1,
                'qualification_op' => QualificationOp::And->value,
                'qualification_rules' => [],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('promotions', [
        'id' => $promotion->id,
        'name' => 'Updated Name',
        'application_budget' => 25,
        'monetary_budget' => 5000,
    ]);
});

it('can update qualification rules replacing old ones', function (): void {
    $discount = MixAndMatchDiscount::query()->create([
        'kind' => MixAndMatchDiscountKind::PercentageOffAllItems,
        'percentage' => 10.0,
    ]);

    $mixAndMatch = MixAndMatchPromotion::query()->create([
        'mix_and_match_discount_id' => $discount->id,
    ]);

    $promotion = Promotion::query()->create([
        'name' => 'Rule Swap',
        'team_id' => $this->team->id,
        'promotionable_type' => $mixAndMatch->getMorphClass(),
        'promotionable_id' => $mixAndMatch->id,
    ]);

    $slot = $mixAndMatch->slots()->create([
        'min' => 1,
        'max' => 1,
        'sort_order' => 0,
    ]);

    $root = $slot->qualification()->create([
        'promotion_id' => $promotion->id,
        'context' => QualificationContext::Primary->value,
        'op' => QualificationOp::And,
        'sort_order' => 0,
    ]);

    $oldRule = $root->rules()->create([
        'kind' => QualificationRuleKind::HasAll,
        'sort_order' => 0,
    ]);

    $oldRule->syncTags(['old-tag']);

    $oldRuleId = $oldRule->id;

    Livewire::test(EditPromotion::class, [
        'record' => $promotion->getRouteKey(),
    ])
        ->fillForm([
            'name' => 'Rule Swap',
            'promotion_type' => PromotionType::MixAndMatch->value,
            'discount_kind' => MixAndMatchDiscountKind::PercentageOffAllItems->value,
            'discount_percentage' => 10.0,
        ])
        ->set('data.slots', [
            [
                'min' => 1,
                'max' => 1,
                'qualification_op' => QualificationOp::Or->value,
                'qualification_rules' => [
                    [
                        'kind' => QualificationRuleKind::HasAny->value,
                        'tags' => ['new-tag'],
                        'group_op' => null,
                        'group_rules' => [],
                    ],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseMissing('qualification_rules', ['id' => $oldRuleId]);

    $this->assertDatabaseHas('qualification_rules', [
        'kind' => QualificationRuleKind::HasAny->value,
    ]);

    $newSlot = $mixAndMatch->slots()->first();
    $newRoot = $newSlot->qualification()->first();

    expect($newRoot->op)->toBe(QualificationOp::Or);

    $newRule = $newRoot->rules()->first();

    expect($newRule->tags->pluck('name')->all())->toBe(['new-tag']);
});

it(
    'cleans up taggable rows when qualification rules are replaced',
    function (): void {
        $discount = MixAndMatchDiscount::query()->create([
            'kind' => MixAndMatchDiscountKind::PercentageOffAllItems,
            'percentage' => 10.0,
        ]);

        $mixAndMatch = MixAndMatchPromotion::query()->create([
            'mix_and_match_discount_id' => $discount->id,
        ]);

        $promotion = Promotion::query()->create([
            'name' => 'Tag Cleanup',
            'team_id' => $this->team->id,
            'promotionable_type' => $mixAndMatch->getMorphClass(),
            'promotionable_id' => $mixAndMatch->id,
        ]);

        $slot = $mixAndMatch->slots()->create([
            'min' => 1,
            'max' => 1,
            'sort_order' => 0,
        ]);

        $root = $slot->qualification()->create([
            'promotion_id' => $promotion->id,
            'context' => QualificationContext::Primary->value,
            'op' => QualificationOp::And,
            'sort_order' => 0,
        ]);

        $rule = $root->rules()->create([
            'kind' => QualificationRuleKind::HasAll,
            'sort_order' => 0,
        ]);

        $rule->syncTags(['cleanup-tag']);

        $oldRuleId = $rule->id;

        Livewire::test(EditPromotion::class, [
            'record' => $promotion->getRouteKey(),
        ])
            ->fillForm([
                'name' => 'Tag Cleanup',
                'promotion_type' => PromotionType::MixAndMatch->value,
                'discount_kind' => MixAndMatchDiscountKind::PercentageOffAllItems->value,
                'discount_percentage' => 10.0,
            ])
            ->set('data.slots', [
                [
                    'min' => 1,
                    'max' => 1,
                    'qualification_op' => QualificationOp::And->value,
                    'qualification_rules' => [],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseMissing('taggables', [
            'taggable_type' => \App\Models\QualificationRule::class,
            'taggable_id' => $oldRuleId,
        ]);
    },
);
