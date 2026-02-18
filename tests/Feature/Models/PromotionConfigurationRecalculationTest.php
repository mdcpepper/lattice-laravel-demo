<?php

namespace Tests\Feature\Models;

use App\Jobs\DispatchCartRecalculationRequest;
use App\Models\Cart;
use App\Models\PromotionLayer;
use App\Models\PromotionStack;
use App\Models\Team;
use Illuminate\Support\Facades\Queue;

it('queues cart recalculation jobs when a promotion is saved', function (): void {
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

    $firstAffectedCart = Cart::factory()->for($team)->create([
        'promotion_stack_id' => $stack->id,
    ]);
    $secondAffectedCart = Cart::factory()->for($team)->create([
        'promotion_stack_id' => $stack->id,
    ]);

    $otherStack = PromotionStack::factory()->for($team)->create();
    $unaffectedCart = Cart::factory()->for($team)->create([
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
});

it('queues cart recalculation jobs when a promotion stack is saved', function (): void {
    Queue::fake();

    $team = Team::factory()->create();

    $stack = PromotionStack::factory()->for($team)->create();

    $firstAffectedCart = Cart::factory()->for($team)->create([
        'promotion_stack_id' => $stack->id,
    ]);
    $secondAffectedCart = Cart::factory()->for($team)->create([
        'promotion_stack_id' => $stack->id,
    ]);

    $otherStack = PromotionStack::factory()->for($team)->create();
    $unaffectedCart = Cart::factory()->for($team)->create([
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
});
