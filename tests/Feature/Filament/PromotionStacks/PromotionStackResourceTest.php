<?php

namespace Tests\Feature\Filament\PromotionStacks;

use App\Enums\SimpleDiscountKind;
use App\Filament\Admin\Resources\PromotionStacks\Pages\CreatePromotionStack;
use App\Filament\Admin\Resources\PromotionStacks\Pages\EditPromotionStack;
use App\Filament\Admin\Resources\PromotionStacks\Pages\ListPromotionStacks;
use App\Filament\Admin\Resources\SimulationRuns\SimulationRunResource;
use App\Models\Cart;
use App\Models\DirectDiscountPromotion;
use App\Models\Promotion;
use App\Models\PromotionLayer;
use App\Models\PromotionStack;
use App\Models\SimpleDiscount;
use App\Models\SimulationRun;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Bus;
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
    Livewire::test(ListPromotionStacks::class)->assertSuccessful();
});

it(
    'redirects to the simulation run page when running a simulation',
    function (): void {
        Bus::fake();

        $stack = PromotionStack::factory()->for($this->team)->create();
        Cart::factory()->for($this->team)->create();

        $livewire = Livewire::test(ListPromotionStacks::class)
            ->callTableAction('runSimulation', record: $stack)
            ->assertHasNoTableActionErrors();

        $simulationRun = SimulationRun::query()
            ->where('promotion_stack_id', $stack->id)
            ->latest('id')
            ->firstOrFail();

        $livewire->assertRedirect(
            SimulationRunResource::getUrl('view', [
                'record' => $simulationRun,
            ]),
        );
    },
);

it(
    'can create a promotion stack with split and pass-through outputs',
    function (): void {
        $promoOne = createDirectPromotion($this->team, 'Promo One');
        $promoTwo = createDirectPromotion($this->team, 'Promo Two');

        Livewire::test(CreatePromotionStack::class)
            ->fillForm([
                'name' => 'Checkout Stack',
                'root_layer_reference' => 'root',
            ])
            ->set('data.layers', [
                [
                    'reference' => 'root',
                    'name' => 'Root Layer',
                    'promotion_ids' => [$promoOne->id],
                    'output_mode' => 'split',
                    'participating_output_mode' => 'layer',
                    'participating_output_reference' => 'eligible',
                    'non_participating_output_mode' => 'pass_through',
                    'non_participating_output_reference' => null,
                ],
                [
                    'reference' => 'eligible',
                    'name' => 'Eligible Layer',
                    'promotion_ids' => [$promoTwo->id],
                    'output_mode' => 'pass_through',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $stack = PromotionStack::query()
            ->where('team_id', $this->team->id)
            ->where('name', 'Checkout Stack')
            ->firstOrFail();

        expect($stack->root_layer_reference)->toBe('root');

        $rootLayer = PromotionLayer::query()
            ->where('promotion_stack_id', $stack->id)
            ->where('reference', 'root')
            ->firstOrFail();
        $eligibleLayer = PromotionLayer::query()
            ->where('promotion_stack_id', $stack->id)
            ->where('reference', 'eligible')
            ->firstOrFail();

        expect($rootLayer->output_mode->value)
            ->toBe('split')
            ->and($rootLayer->participating_output_layer_id)
            ->toBe($eligibleLayer->id)
            ->and($rootLayer->non_participating_output_layer_id)
            ->toBeNull()
            ->and($eligibleLayer->output_mode->value)
            ->toBe('pass_through');

        $this->assertDatabaseHas('promotion_layer_promotion', [
            'promotion_layer_id' => $rootLayer->id,
            'promotion_id' => $promoOne->id,
        ]);

        $this->assertDatabaseHas('promotion_layer_promotion', [
            'promotion_layer_id' => $eligibleLayer->id,
            'promotion_id' => $promoTwo->id,
        ]);
    },
);

it('can update a stack graph in the edit page', function (): void {
    $promoOne = createDirectPromotion($this->team, 'Promo One');
    $promoTwo = createDirectPromotion($this->team, 'Promo Two');
    $promoThree = createDirectPromotion($this->team, 'Promo Three');

    $stack = PromotionStack::query()->create([
        'team_id' => $this->team->id,
        'name' => 'Editable Stack',
        'root_layer_reference' => 'root',
    ]);

    $eligibleLayer = $stack->layers()->create([
        'reference' => 'eligible',
        'name' => 'Eligible',
        'sort_order' => 1,
        'output_mode' => 'pass_through',
    ]);

    $rootLayer = $stack->layers()->create([
        'reference' => 'root',
        'name' => 'Root',
        'sort_order' => 0,
        'output_mode' => 'split',
        'participating_output_mode' => 'layer',
        'participating_output_layer_id' => $eligibleLayer->id,
        'non_participating_output_mode' => 'pass_through',
    ]);

    $rootLayer->promotions()->sync([$promoOne->id => ['sort_order' => 0]]);
    $eligibleLayer->promotions()->sync([$promoTwo->id => ['sort_order' => 0]]);

    Livewire::test(EditPromotionStack::class, ['record' => $stack->ulid])
        ->fillForm([
            'name' => 'Editable Stack Updated',
            'root_layer_reference' => 'root',
        ])
        ->set('data.layers', [
            [
                'reference' => 'root',
                'name' => 'Root',
                'promotion_ids' => [$promoOne->id],
                'output_mode' => 'split',
                'participating_output_mode' => 'layer',
                'participating_output_reference' => 'eligible',
                'non_participating_output_mode' => 'layer',
                'non_participating_output_reference' => 'fallback',
            ],
            [
                'reference' => 'eligible',
                'name' => 'Eligible',
                'promotion_ids' => [$promoTwo->id],
                'output_mode' => 'pass_through',
            ],
            [
                'reference' => 'fallback',
                'name' => 'Fallback',
                'promotion_ids' => [$promoThree->id],
                'output_mode' => 'pass_through',
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $stack->refresh();

    expect($stack->name)
        ->toBe('Editable Stack Updated')
        ->and($stack->root_layer_reference)
        ->toBe('root');

    $rootLayer = PromotionLayer::query()
        ->where('promotion_stack_id', $stack->id)
        ->where('reference', 'root')
        ->firstOrFail();
    $fallbackLayer = PromotionLayer::query()
        ->where('promotion_stack_id', $stack->id)
        ->where('reference', 'fallback')
        ->firstOrFail();

    expect($rootLayer->non_participating_output_layer_id)->toBe(
        $fallbackLayer->id,
    );

    $this->assertDatabaseHas('promotion_layer_promotion', [
        'promotion_layer_id' => $fallbackLayer->id,
        'promotion_id' => $promoThree->id,
    ]);
});

it('validates duplicate layer references', function (): void {
    Livewire::test(CreatePromotionStack::class)
        ->fillForm([
            'name' => 'Invalid Stack',
            'root_layer_reference' => 'root',
        ])
        ->set('data.layers', [
            [
                'reference' => 'root',
                'name' => 'Root One',
                'promotion_ids' => [],
                'output_mode' => 'pass_through',
            ],
            [
                'reference' => 'root',
                'name' => 'Root Two',
                'promotion_ids' => [],
                'output_mode' => 'pass_through',
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['layers']);
});

function createDirectPromotion(Team $team, string $name): Promotion
{
    $discount = SimpleDiscount::query()->create([
        'kind' => SimpleDiscountKind::PercentageOff,
        'percentage' => 10,
    ]);

    $direct = DirectDiscountPromotion::query()->create([
        'simple_discount_id' => $discount->id,
    ]);

    return Promotion::query()->create([
        'team_id' => $team->id,
        'name' => $name,
        'promotionable_type' => $direct->getMorphClass(),
        'promotionable_id' => $direct->id,
    ]);
}
