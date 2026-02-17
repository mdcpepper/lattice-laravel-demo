<?php

namespace Tests\Feature\Jobs;

use App\Enums\BacktestStatus;
use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Enums\SimpleDiscountKind;
use App\Jobs\ProcessCartBacktestJob;
use App\Models\Backtest;
use App\Models\BacktestedCartItem;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DirectDiscountPromotion;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionLayer;
use App\Models\PromotionStack;
use App\Models\SimpleDiscount;
use App\Models\Team;
use App\Services\Lattice\Stacks\LatticeStackFactory;

it('processes a cart and creates backtest records', function (): void {
    $team = Team::factory()->create();

    $product = Product::factory()
        ->for($team)
        ->create(['price' => 5_00]);

    $product->syncTags(['sale']);
    $product->load('tags');

    $discount = SimpleDiscount::query()->create([
        'kind' => SimpleDiscountKind::PercentageOff,
        'percentage' => 10.0,
    ]);

    $direct = DirectDiscountPromotion::query()->create([
        'simple_discount_id' => $discount->id,
    ]);

    $promotion = Promotion::query()->create([
        'name' => '10% Off Sale Items',
        'team_id' => $team->id,
        'promotionable_type' => $direct->getMorphClass(),
        'promotionable_id' => $direct->id,
    ]);

    $qualification = $direct->qualification()->create([
        'promotion_id' => $promotion->id,
        'context' => 'primary',
        'op' => QualificationOp::And,
        'sort_order' => 0,
    ]);

    $rule = $qualification->rules()->create([
        'kind' => QualificationRuleKind::HasAny,
        'sort_order' => 0,
    ]);

    $rule->syncTags(['sale']);

    $stack = PromotionStack::factory()->for($team)->create();

    $layer = PromotionLayer::factory()
        ->for($stack, 'stack')
        ->create([
            'reference' => 'root',
            'sort_order' => 0,
        ]);

    $layer->promotions()->attach($promotion, ['sort_order' => 0]);

    $cart = Cart::factory()->for($team)->create();
    $cartItem = CartItem::factory()
        ->for($cart)
        ->for($product)
        ->create([
            'subtotal' => 500,
            'subtotal_currency' => 'GBP',
            'total' => 500,
            'total_currency' => 'GBP',
        ]);

    $backtest = Backtest::query()->create([
        'promotion_stack_id' => $stack->id,
        'total_carts' => 1,
        'processed_carts' => 0,
        'status' => BacktestStatus::Running,
    ]);

    $job = new ProcessCartBacktestJob(
        backtestRunId: $backtest->id,
        cartId: $cart->id,
    );

    $job->handle(app(LatticeStackFactory::class));

    $this->assertDatabaseHas('backtested_carts', [
        'backtest_id' => $backtest->id,
        'cart_id' => $cart->id,
        'team_id' => $team->id,
        'subtotal' => 500,
        'subtotal_currency' => 'GBP',
        'total' => 450,
        'total_currency' => 'GBP',
    ]);

    $this->assertDatabaseHas('backtested_cart_items', [
        'backtest_id' => $backtest->id,
        'cart_item_id' => $cartItem->id,
        'product_id' => $product->id,
        'subtotal' => 500,
        'total' => 450,
    ]);

    $simulatedCartItem = BacktestedCartItem::query()
        ->where('cart_item_id', $cartItem->id)
        ->firstOrFail();

    $this->assertDatabaseHas('promotion_redemptions', [
        'promotion_stack_id' => $stack->id,
        'redeemable_type' => BacktestedCartItem::class,
        'redeemable_id' => $simulatedCartItem->id,
        'sort_order' => 0,
        'original_price' => 500,
        'final_price' => 450,
    ]);

    $backtest->refresh();

    expect($backtest->processed_carts)
        ->toBe(1)
        ->and($backtest->status)
        ->toBe(BacktestStatus::Completed);
});
