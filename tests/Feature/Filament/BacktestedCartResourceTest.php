<?php

namespace Tests\Feature\Filament;

use App\Enums\BacktestStatus;
use App\Enums\SimpleDiscountKind;
use App\Filament\Admin\Resources\BacktestedCarts\Pages\ViewBacktestedCart;
use App\Filament\Admin\Resources\BacktestedCarts\RelationManagers\ItemsRelationManager;
use App\Filament\Admin\Resources\Backtests\Pages\ViewBacktest;
use App\Filament\Admin\Resources\Backtests\RelationManagers\BacktestedCartsRelationManager;
use App\Models\Backtest;
use App\Models\BacktestedCart;
use App\Models\BacktestedCartItem;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DirectDiscountPromotion;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\PromotionStack;
use App\Models\SimpleDiscount;
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
    'shows promotion names in backtested cart items relation manager',
    function (): void {
        $stack = PromotionStack::factory()->for($this->team)->create();
        $backtest = Backtest::query()->create([
            'promotion_stack_id' => $stack->id,
            'total_carts' => 1,
            'processed_carts' => 1,
            'status' => BacktestStatus::Completed,
        ]);

        $cart = Cart::factory()->for($this->team)->create();
        $backtestedCart = BacktestedCart::query()->create([
            'backtest_id' => $backtest->id,
            'cart_id' => $cart->id,
            'team_id' => $this->team->id,
            'subtotal' => 1000,
            'subtotal_currency' => 'GBP',
            'total' => 900,
            'total_currency' => 'GBP',
        ]);

        $product = Product::factory()->for($this->team)->create();
        $cartItem = CartItem::factory()->for($cart)->for($product)->create();

        $backtestedCartItem = BacktestedCartItem::query()->create([
            'backtest_id' => $backtest->id,
            'backtested_cart_id' => $backtestedCart->id,
            'cart_item_id' => $cartItem->id,
            'product_id' => $product->id,
            'price' => 1000,
            'price_currency' => 'GBP',
            'offer_price' => 900,
            'offer_price_currency' => 'GBP',
        ]);

        $discount = SimpleDiscount::query()->create([
            'kind' => SimpleDiscountKind::PercentageOff,
            'percentage' => 10,
        ]);

        $directDiscount = DirectDiscountPromotion::query()->create([
            'simple_discount_id' => $discount->id,
        ]);

        $promotion = Promotion::query()->create([
            'team_id' => $this->team->id,
            'name' => 'Backtest Promo',
            'promotionable_type' => $directDiscount->getMorphClass(),
            'promotionable_id' => $directDiscount->id,
        ]);

        PromotionRedemption::query()->create([
            'promotion_id' => $promotion->id,
            'promotion_stack_id' => $stack->id,
            'redeemable_type' => BacktestedCartItem::class,
            'redeemable_id' => $backtestedCartItem->id,
            'sort_order' => 0,
            'original_price' => 1000,
            'original_price_currency' => 'GBP',
            'final_price' => 900,
            'final_price_currency' => 'GBP',
        ]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $backtestedCart,
            'pageClass' => ViewBacktestedCart::class,
        ])
            ->assertSee('Promotion')
            ->assertSee('Backtest Promo');
    },
);

it(
    'shows promotion names in backtested carts relation manager',
    function (): void {
        $stack = PromotionStack::factory()->for($this->team)->create();
        $backtest = Backtest::query()->create([
            'promotion_stack_id' => $stack->id,
            'total_carts' => 1,
            'processed_carts' => 1,
            'status' => BacktestStatus::Completed,
        ]);

        $cart = Cart::factory()->for($this->team)->create();
        $backtestedCart = BacktestedCart::query()->create([
            'backtest_id' => $backtest->id,
            'cart_id' => $cart->id,
            'team_id' => $this->team->id,
            'subtotal' => 1000,
            'subtotal_currency' => 'GBP',
            'total' => 900,
            'total_currency' => 'GBP',
        ]);

        $product = Product::factory()->for($this->team)->create();
        $cartItem = CartItem::factory()->for($cart)->for($product)->create();

        $backtestedCartItem = BacktestedCartItem::query()->create([
            'backtest_id' => $backtest->id,
            'backtested_cart_id' => $backtestedCart->id,
            'cart_item_id' => $cartItem->id,
            'product_id' => $product->id,
            'price' => 1000,
            'price_currency' => 'GBP',
            'offer_price' => 900,
            'offer_price_currency' => 'GBP',
        ]);

        $discount = SimpleDiscount::query()->create([
            'kind' => SimpleDiscountKind::PercentageOff,
            'percentage' => 10,
        ]);

        $directDiscount = DirectDiscountPromotion::query()->create([
            'simple_discount_id' => $discount->id,
        ]);

        $promotion = Promotion::query()->create([
            'team_id' => $this->team->id,
            'name' => 'Backtest Promo',
            'promotionable_type' => $directDiscount->getMorphClass(),
            'promotionable_id' => $directDiscount->id,
        ]);

        PromotionRedemption::query()->create([
            'promotion_id' => $promotion->id,
            'promotion_stack_id' => $stack->id,
            'redeemable_type' => BacktestedCartItem::class,
            'redeemable_id' => $backtestedCartItem->id,
            'sort_order' => 0,
            'original_price' => 1000,
            'original_price_currency' => 'GBP',
            'final_price' => 900,
            'final_price_currency' => 'GBP',
        ]);

        Livewire::test(BacktestedCartsRelationManager::class, [
            'ownerRecord' => $backtest,
            'pageClass' => ViewBacktest::class,
        ])
            ->assertSee('Promotion(s)')
            ->assertSee('Backtest Promo');
    },
);
