<?php

namespace Tests\Feature\Models;

use App\Jobs\DispatchCartRecalculationRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\PromotionLayer;
use App\Models\PromotionStack;
use App\Models\Team;
use Illuminate\Support\Facades\Queue;

it(
    'queues cart recalculation jobs when a promotion is saved',
    function (): void {
        Queue::fake();

        $team = Team::factory()->create();
        $promotion = createSaleDiscountPromotion($team);

        $stack = PromotionStack::factory()->for($team)->create();

        $layer = PromotionLayer::factory()
            ->for($stack, 'stack')
            ->create([
                'reference' => 'root',
                'sort_order' => 0,
            ]);

        $layer->promotions()->attach($promotion->id, ['sort_order' => 0]);

        $firstAffectedCart = Cart::factory()
            ->for($team)
            ->create([
                'promotion_stack_id' => $stack->id,
            ]);
        $secondAffectedCart = Cart::factory()
            ->for($team)
            ->create([
                'promotion_stack_id' => $stack->id,
            ]);

        $otherStack = PromotionStack::factory()->for($team)->create();
        $unaffectedCart = Cart::factory()
            ->for($team)
            ->create([
                'promotion_stack_id' => $otherStack->id,
            ]);

        $promotion->update(['name' => 'Updated Promotion Name']);

        Queue::assertPushed(DispatchCartRecalculationRequest::class, 2);
        Queue::assertPushed(
            DispatchCartRecalculationRequest::class,
            fn (DispatchCartRecalculationRequest $job): bool => $job->cartId ===
                $firstAffectedCart->id,
        );
        Queue::assertPushed(
            DispatchCartRecalculationRequest::class,
            fn (DispatchCartRecalculationRequest $job): bool => $job->cartId ===
                $secondAffectedCart->id,
        );
        Queue::assertNotPushed(
            DispatchCartRecalculationRequest::class,
            fn (DispatchCartRecalculationRequest $job): bool => $job->cartId ===
                $unaffectedCart->id,
        );
    },
);

it(
    'queues cart recalculation jobs when a promotion stack is saved',
    function (): void {
        Queue::fake();

        $team = Team::factory()->create();

        $stack = PromotionStack::factory()->for($team)->create();

        $firstAffectedCart = Cart::factory()
            ->for($team)
            ->create([
                'promotion_stack_id' => $stack->id,
            ]);
        $secondAffectedCart = Cart::factory()
            ->for($team)
            ->create([
                'promotion_stack_id' => $stack->id,
            ]);

        $otherStack = PromotionStack::factory()->for($team)->create();
        $unaffectedCart = Cart::factory()
            ->for($team)
            ->create([
                'promotion_stack_id' => $otherStack->id,
            ]);

        $stack->update(['name' => 'Updated Stack Name']);

        Queue::assertPushed(DispatchCartRecalculationRequest::class, 2);
        Queue::assertPushed(
            DispatchCartRecalculationRequest::class,
            fn (DispatchCartRecalculationRequest $job): bool => $job->cartId ===
                $firstAffectedCart->id,
        );
        Queue::assertPushed(
            DispatchCartRecalculationRequest::class,
            fn (DispatchCartRecalculationRequest $job): bool => $job->cartId ===
                $secondAffectedCart->id,
        );
        Queue::assertNotPushed(
            DispatchCartRecalculationRequest::class,
            fn (DispatchCartRecalculationRequest $job): bool => $job->cartId ===
                $unaffectedCart->id,
        );
    },
);

it(
    'queues cart recalculation jobs when a product tag assignment changes',
    function (): void {
        $team = Team::factory()->create();

        $product = Product::factory()->for($team)->create();
        $product->syncTags(['eligible']);

        $firstAffectedCart = Cart::factory()->for($team)->create();
        CartItem::factory()->for($firstAffectedCart)->for($product)->create();

        $secondAffectedCart = Cart::factory()->for($team)->create();
        CartItem::factory()->for($secondAffectedCart)->for($product)->create();

        $otherProduct = Product::factory()->for($team)->create();
        $unaffectedCart = Cart::factory()->for($team)->create();
        CartItem::factory()->for($unaffectedCart)->for($otherProduct)->create();

        Queue::fake();

        $product->syncTags(['seasonal']);

        Queue::assertPushed(DispatchCartRecalculationRequest::class, 2);

        Queue::assertPushed(
            DispatchCartRecalculationRequest::class,
            fn (DispatchCartRecalculationRequest $job): bool => $job->cartId ===
                $firstAffectedCart->id,
        );

        Queue::assertPushed(
            DispatchCartRecalculationRequest::class,
            fn (DispatchCartRecalculationRequest $job): bool => $job->cartId ===
                $secondAffectedCart->id,
        );

        Queue::assertNotPushed(
            DispatchCartRecalculationRequest::class,
            fn (DispatchCartRecalculationRequest $job): bool => $job->cartId ===
                $unaffectedCart->id,
        );
    },
);

it(
    'does not queue cart recalculation jobs when a product tag assignment is unchanged',
    function (): void {
        $team = Team::factory()->create();

        $product = Product::factory()->for($team)->create();
        $product->syncTags(['eligible']);

        $affectedCart = Cart::factory()->for($team)->create();
        CartItem::factory()->for($affectedCart)->for($product)->create();

        Queue::fake();

        $product->syncTags(['eligible']);

        Queue::assertNotPushed(DispatchCartRecalculationRequest::class);
    },
);
