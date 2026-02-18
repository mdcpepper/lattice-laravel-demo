<?php

use App\Events\CartRecalculationRequested;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\PromotionLayer;
use App\Models\PromotionRedemption;
use App\Models\PromotionStack;
use App\Models\Team;

it(
    'recalculates cart and cart item totals through the assigned stack',
    function (): void {
        $team = Team::factory()->create();

        $product = Product::factory()
            ->for($team)
            ->create(['price' => 5_00]);
        $product->syncTags(['sale']);
        $product->load('tags');

        $promotion = createSaleDiscountPromotion($team);

        $stack = PromotionStack::factory()->for($team)->create();

        $layer = PromotionLayer::factory()
            ->for($stack, 'stack')
            ->create([
                'reference' => 'root',
                'sort_order' => 0,
            ]);

        $layer->promotions()->attach($promotion, ['sort_order' => 0]);

        $cart = Cart::factory()
            ->for($team)
            ->create([
                'promotion_stack_id' => $stack->id,
            ]);

        $cartItem = CartItem::factory()
            ->for($cart)
            ->for($product)
            ->create([
                'price' => 999,
                'price_currency' => 'GBP',
                'offer_price' => 999,
                'offer_price_currency' => 'GBP',
            ]);

        CartRecalculationRequested::dispatch($cart->id);

        $this->assertDatabaseHas('carts', [
            'id' => $cart->id,
            'subtotal' => 500,
            'subtotal_currency' => 'GBP',
            'total' => 450,
            'total_currency' => 'GBP',
        ]);

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'price' => 500,
            'price_currency' => 'GBP',
            'offer_price' => 450,
            'offer_price_currency' => 'GBP',
        ]);

        $this->assertDatabaseHas('promotion_redemptions', [
            'promotion_id' => $promotion->id,
            'promotion_stack_id' => $stack->id,
            'redeemable_type' => CartItem::class,
            'redeemable_id' => $cartItem->id,
            'sort_order' => 0,
            'original_price' => 500,
            'original_price_currency' => 'GBP',
            'final_price' => 450,
            'final_price_currency' => 'GBP',
        ]);
    },
);

it(
    'clears cart item redemptions when the cart has no assigned stack',
    function (): void {
        $team = Team::factory()->create();

        $product = Product::factory()
            ->for($team)
            ->create(['price' => 5_00]);

        $product->syncTags(['sale']);
        $product->load('tags');

        $promotion = createSaleDiscountPromotion($team);

        $stack = PromotionStack::factory()->for($team)->create();

        $layer = PromotionLayer::factory()
            ->for($stack, 'stack')
            ->create([
                'reference' => 'root',
                'sort_order' => 0,
            ]);

        $layer->promotions()->attach($promotion, ['sort_order' => 0]);

        $cart = Cart::factory()
            ->for($team)
            ->create([
                'promotion_stack_id' => $stack->id,
            ]);

        $cartItem = CartItem::factory()
            ->for($cart)
            ->for($product)
            ->create([
                'price' => 500,
                'price_currency' => 'GBP',
                'offer_price' => 500,
                'offer_price_currency' => 'GBP',
            ]);

        CartRecalculationRequested::dispatch($cart->id);

        expect(
            PromotionRedemption::query()
                ->where('redeemable_type', CartItem::class)
                ->where('redeemable_id', $cartItem->id)
                ->count(),
        )->toBe(1);

        $cart->update(['promotion_stack_id' => null]);

        CartRecalculationRequested::dispatch($cart->id);

        $this->assertDatabaseHas('carts', [
            'id' => $cart->id,
            'subtotal' => 500,
            'subtotal_currency' => 'GBP',
            'total' => 500,
            'total_currency' => 'GBP',
        ]);

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'price' => 500,
            'price_currency' => 'GBP',
            'offer_price' => 500,
            'offer_price_currency' => 'GBP',
        ]);

        expect(
            PromotionRedemption::query()
                ->where('redeemable_type', CartItem::class)
                ->where('redeemable_id', $cartItem->id)
                ->count(),
        )->toBe(0);
    },
);

it(
    'enforces application budget across separate carts during recalculation',
    function (): void {
        $team = Team::factory()->create();

        $product = Product::factory()
            ->for($team)
            ->create(['price' => 5_00]);
        $product->syncTags(['sale']);
        $product->load('tags');

        $promotion = createSaleDiscountPromotion($team);
        $promotion->update(['application_budget' => 1]);

        $stack = PromotionStack::factory()->for($team)->create();

        $layer = PromotionLayer::factory()
            ->for($stack, 'stack')
            ->create([
                'reference' => 'root',
                'sort_order' => 0,
            ]);

        $layer->promotions()->attach($promotion, ['sort_order' => 0]);

        $cartOne = Cart::factory()
            ->for($team)
            ->create([
                'promotion_stack_id' => $stack->id,
            ]);

        $cartOneItem = CartItem::factory()
            ->for($cartOne)
            ->for($product)
            ->create([
                'price' => 999,
                'price_currency' => 'GBP',
                'offer_price' => 999,
                'offer_price_currency' => 'GBP',
            ]);

        $cartTwo = Cart::factory()
            ->for($team)
            ->create([
                'promotion_stack_id' => $stack->id,
            ]);

        $cartTwoItem = CartItem::factory()
            ->for($cartTwo)
            ->for($product)
            ->create([
                'price' => 999,
                'price_currency' => 'GBP',
                'offer_price' => 999,
                'offer_price_currency' => 'GBP',
            ]);

        CartRecalculationRequested::dispatch($cartOne->id);

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartOneItem->id,
            'price' => 500,
            'price_currency' => 'GBP',
            'offer_price' => 450,
            'offer_price_currency' => 'GBP',
        ]);

        $this->assertDatabaseHas('promotion_redemptions', [
            'promotion_id' => $promotion->id,
            'promotion_stack_id' => $stack->id,
            'redeemable_type' => CartItem::class,
            'redeemable_id' => $cartOneItem->id,
        ]);

        CartRecalculationRequested::dispatch($cartTwo->id);

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartTwoItem->id,
            'price' => 500,
            'price_currency' => 'GBP',
            'offer_price' => 500,
            'offer_price_currency' => 'GBP',
        ]);

        $this->assertDatabaseMissing('promotion_redemptions', [
            'promotion_id' => $promotion->id,
            'promotion_stack_id' => $stack->id,
            'redeemable_type' => CartItem::class,
            'redeemable_id' => $cartTwoItem->id,
        ]);
    },
);
