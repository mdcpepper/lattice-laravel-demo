<?php

namespace Tests\Feature\Filament;

use App\Enums\PromotionType;
use App\Enums\QualificationContext;
use App\Enums\QualificationOp;
use App\Enums\SimpleDiscountKind;
use App\Filament\Admin\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Admin\Resources\Promotions\Pages\EditPromotion;
use App\Models\PositionalDiscountPromotion;
use App\Models\Promotion;
use App\Models\SimpleDiscount;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it(
    'stores 1-based selected positions as 0-based values on create',
    function (): void {
        Livewire::test(CreatePromotion::class)
            ->fillForm([
                'name' => 'Positional 5',
                'promotion_type' => PromotionType::PositionalDiscount->value,
                'size' => 5,
                'discount_kind' => SimpleDiscountKind::PercentageOff->value,
                'discount_percentage' => 10.0,
                'qualification_op' => QualificationOp::And->value,
                'qualification_rules' => [],
                'positions' => [
                    ['position' => 1],
                    ['position' => 3],
                    ['position' => 5],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $promotion = Promotion::query()
            ->where('name', 'Positional 5')
            ->firstOrFail();

        $positional = $promotion->promotionable;

        expect($positional)
            ->toBeInstanceOf(PositionalDiscountPromotion::class)
            ->and($positional->size)
            ->toBe(5)
            ->and(
                $positional
                    ->positions()
                    ->orderBy('sort_order')
                    ->pluck('position')
                    ->all(),
            )
            ->toBe([0, 2, 4]);
    },
);

it(
    'validates positional selections are within 1..size on create',
    function (): void {
        Livewire::test(CreatePromotion::class)
            ->fillForm([
                'name' => 'Invalid Positional',
                'promotion_type' => PromotionType::PositionalDiscount->value,
                'size' => 3,
                'discount_kind' => SimpleDiscountKind::PercentageOff->value,
                'discount_percentage' => 10.0,
                'qualification_op' => QualificationOp::And->value,
                'qualification_rules' => [],
                'positions' => [['position' => 4]],
            ])
            ->call('create')
            ->assertHasFormErrors(['positions.0.position']);
    },
);

it('validates positional selections are distinct on create', function (): void {
    Livewire::test(CreatePromotion::class)
        ->fillForm([
            'name' => 'Duplicate Positional',
            'promotion_type' => PromotionType::PositionalDiscount->value,
            'size' => 5,
            'discount_kind' => SimpleDiscountKind::PercentageOff->value,
            'discount_percentage' => 10.0,
            'qualification_op' => QualificationOp::And->value,
            'qualification_rules' => [],
            'positions' => [['position' => 2], ['position' => 2]],
        ])
        ->call('create')
        ->assertHasFormErrors(['positions.1.position']);
});

it(
    'loads stored 0-based positions as 1-based values in edit form',
    function (): void {
        $discount = SimpleDiscount::query()->create([
            'kind' => SimpleDiscountKind::PercentageOff,
            'percentage' => 10.0,
        ]);

        $positional = PositionalDiscountPromotion::query()->create([
            'simple_discount_id' => $discount->id,
            'size' => 4,
        ]);

        $promotion = Promotion::query()->create([
            'name' => 'Edit Positional',
            'promotionable_type' => $positional->getMorphClass(),
            'promotionable_id' => $positional->id,
        ]);

        $positional->qualification()->create([
            'promotion_id' => $promotion->id,
            'context' => QualificationContext::Primary->value,
            'op' => QualificationOp::And,
            'sort_order' => 0,
        ]);

        $positional
            ->positions()
            ->createMany([
                ['position' => 0, 'sort_order' => 0],
                ['position' => 2, 'sort_order' => 1],
            ]);

        $component = Livewire::test(EditPromotion::class, [
            'record' => $promotion->id,
        ])
            ->assertSet(
                'data.promotion_type',
                PromotionType::PositionalDiscount->value,
            )
            ->assertSet('data.size', 4);

        $positions = array_values($component->get('data.positions'));

        expect($positions)
            ->toHaveCount(2)
            ->and($positions[0]['position'])
            ->toBe('1')
            ->and($positions[1]['position'])
            ->toBe('3');
    },
);

it('stores edited 1-based positions as 0-based values', function (): void {
    $discount = SimpleDiscount::query()->create([
        'kind' => SimpleDiscountKind::PercentageOff,
        'percentage' => 10.0,
    ]);

    $positional = PositionalDiscountPromotion::query()->create([
        'simple_discount_id' => $discount->id,
        'size' => 3,
    ]);

    $promotion = Promotion::query()->create([
        'name' => 'Update Positional',
        'promotionable_type' => $positional->getMorphClass(),
        'promotionable_id' => $positional->id,
    ]);

    $positional->qualification()->create([
        'promotion_id' => $promotion->id,
        'context' => QualificationContext::Primary->value,
        'op' => QualificationOp::And,
        'sort_order' => 0,
    ]);

    $positional->positions()->create([
        'position' => 0,
        'sort_order' => 0,
    ]);

    Livewire::test(EditPromotion::class, ['record' => $promotion->id])
        ->fillForm([
            'name' => 'Updated Positional',
            'promotion_type' => PromotionType::PositionalDiscount->value,
            'size' => 5,
            'discount_kind' => SimpleDiscountKind::PercentageOff->value,
            'discount_percentage' => 20.0,
            'qualification_op' => QualificationOp::And->value,
            'qualification_rules' => [],
        ])
        ->set('data.positions', [['position' => 2], ['position' => 5]])
        ->call('save')
        ->assertHasNoFormErrors();

    $positional->refresh();

    expect($positional->size)
        ->toBe(5)
        ->and(
            $positional
                ->positions()
                ->orderBy('sort_order')
                ->pluck('position')
                ->all(),
        )
        ->toBe([1, 4]);
});
