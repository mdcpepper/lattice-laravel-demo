<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Lattice;

use App\Enums\QualificationContext;
use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Enums\SimpleDiscountKind;
use App\Models\DirectDiscountPromotion;
use App\Models\Promotion;
use App\Models\PromotionStack;
use App\Models\SimpleDiscount;
use App\Models\Team;
use App\Services\Lattice\Stacks\LatticeStackFactory;
use Lattice\Item;
use Lattice\Money;
use Lattice\Product;
use RuntimeException;

test(
    'builds a lattice stack from persisted layers and processes split outputs with pass-through branches',
    function (): void {
        $team = Team::factory()->create();

        $rootPromotion = createDirectPromotionWithQualification(
            team: $team,
            name: 'Eligible Root Promotion',
            ruleKind: QualificationRuleKind::HasAll,
            tags: ['eligible'],
            percentage: 10.0,
        );

        $participatingPromotion = createDirectPromotionWithQualification(
            team: $team,
            name: 'Participating Branch Promotion',
            ruleKind: QualificationRuleKind::HasNone,
            tags: ['never'],
            percentage: 10.0,
        );

        $stack = PromotionStack::query()->create([
            'team_id' => $team->id,
            'name' => 'Checkout Stack',
            'root_layer_reference' => 'root',
        ]);

        $participatingLayer = $stack->layers()->create([
            'reference' => 'participating',
            'name' => 'Participating',
            'sort_order' => 1,
            'output_mode' => 'pass_through',
        ]);

        $rootLayer = $stack->layers()->create([
            'reference' => 'root',
            'name' => 'Root Layer',
            'sort_order' => 0,
            'output_mode' => 'split',
            'participating_output_mode' => 'layer',
            'participating_output_layer_id' => $participatingLayer->id,
            'non_participating_output_mode' => 'pass_through',
        ]);

        $rootLayer->promotions()->sync([
            $rootPromotion->id => ['sort_order' => 0],
        ]);

        $participatingLayer->promotions()->sync([
            $participatingPromotion->id => ['sort_order' => 0],
        ]);

        $stackFactory = app(LatticeStackFactory::class);
        $latticeStack = $stackFactory->make($stack);

        expect($latticeStack->validateGraph())->toBeTrue();

        $eligibleItem = Item::fromProduct(
            reference: 'eligible-item',
            product: new Product(
                'sku-eligible',
                'Eligible Product',
                new Money(100, 'GBP'),
                ['eligible'],
            ),
        );

        $regularItem = Item::fromProduct(
            reference: 'regular-item',
            product: new Product(
                'sku-regular',
                'Regular Product',
                new Money(100, 'GBP'),
                [],
            ),
        );

        $receipt = $latticeStack->process([$eligibleItem, $regularItem]);

        expect($receipt->subtotal->amount)
            ->toBe(200)
            ->and($receipt->total->amount)
            ->toBe(181)
            ->and($receipt->promotionRedemptions)
            ->toHaveCount(2);
    },
);

test(
    'throws when the configured root layer cannot be found',
    function (): void {
        $team = Team::factory()->create();

        $stack = PromotionStack::query()->create([
            'team_id' => $team->id,
            'name' => 'Broken Stack',
            'root_layer_reference' => 'missing-root',
        ]);

        $stack->layers()->create([
            'reference' => 'root',
            'name' => 'Root Layer',
            'sort_order' => 0,
            'output_mode' => 'pass_through',
        ]);

        $stackFactory = app(LatticeStackFactory::class);

        expect(fn (): mixed => $stackFactory->make($stack))->toThrow(
            RuntimeException::class,
            'Promotion stack root layer [missing-root] was not found.',
        );
    },
);

function createDirectPromotionWithQualification(
    Team $team,
    string $name,
    QualificationRuleKind $ruleKind,
    array $tags,
    float $percentage,
): Promotion {
    $discount = SimpleDiscount::query()->create([
        'kind' => SimpleDiscountKind::PercentageOff,
        'percentage' => $percentage,
    ]);

    $direct = DirectDiscountPromotion::query()->create([
        'simple_discount_id' => $discount->id,
    ]);

    $promotion = Promotion::query()->create([
        'team_id' => $team->id,
        'name' => $name,
        'promotionable_type' => $direct->getMorphClass(),
        'promotionable_id' => $direct->id,
    ]);

    $qualification = $direct->qualification()->create([
        'promotion_id' => $promotion->id,
        'context' => QualificationContext::Primary->value,
        'op' => QualificationOp::And->value,
        'sort_order' => 0,
    ]);

    $rule = $qualification->rules()->create([
        'kind' => $ruleKind->value,
        'sort_order' => 0,
    ]);

    $rule->syncTags($tags);

    return $promotion;
}
