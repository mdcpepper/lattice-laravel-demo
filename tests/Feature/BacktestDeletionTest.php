<?php

use App\Models\Backtest;
use App\Models\BacktestedCart;
use App\Models\BacktestedCartItem;
use App\Models\PromotionRedemption;
use App\Models\PromotionStack;
use App\Models\Team;

it(
    'deletes promotion redemptions when a backtest is deleted',
    function (): void {
        $team = Team::factory()->create();
        $promotion = createSaleDiscountPromotion($team);

        $stack = PromotionStack::factory()->for($team)->create();
        $backtest = Backtest::factory()->for($stack)->create();
        $backtestedCart = BacktestedCart::factory()->for($backtest)->create();
        $item = BacktestedCartItem::factory()
            ->for($backtestedCart)
            ->create(['backtest_id' => $backtest->id]);

        PromotionRedemption::factory()->create([
            'promotion_id' => $promotion->id,
            'promotion_stack_id' => $stack->id,
            'redeemable_type' => $item->getMorphClass(),
            'redeemable_id' => $item->id,
        ]);

        expect(PromotionRedemption::count())->toBe(1);

        $backtest->delete();

        expect(PromotionRedemption::count())->toBe(0);
        expect(BacktestedCartItem::count())->toBe(0);
        expect(BacktestedCart::count())->toBe(0);
    },
);
