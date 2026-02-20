<?php

namespace Tests\Feature\Filament;

use App\Enums\BacktestStatus;
use App\Enums\SimpleDiscountKind;
use App\Filament\Admin\Resources\BacktestedCarts\Pages\ViewBacktestedCart;
use App\Filament\Admin\Resources\BacktestedCarts\RelationManagers\ItemsRelationManager;
use App\Filament\Admin\Resources\Backtests\Pages\ViewBacktest;
use App\Filament\Admin\Resources\Backtests\RelationManagers\BacktestedCartsRelationManager;
use App\Filament\Admin\Resources\Backtests\Widgets\BacktestStatsWidget;
use App\Models\Backtests\Backtest;
use App\Models\Backtests\BacktestedCart;
use App\Models\Backtests\BacktestedCartItem;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Product;
use App\Models\Promotions\DirectDiscountPromotion;
use App\Models\Promotions\Promotion;
use App\Models\Promotions\PromotionRedemption;
use App\Models\Promotions\PromotionStack;
use App\Models\Promotions\SimpleDiscount;
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
    'shows cart summary stats on the backtested cart view page',
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
            'subtotal' => 1_000,
            'subtotal_currency' => 'GBP',
            'total' => 750,
            'total_currency' => 'GBP',
            'processing_time' => 900,
            'solve_time' => 800,
        ]);

        $products = Product::factory()
            ->count(3)
            ->for($this->team)
            ->create([
                'price' => 500,
            ]);

        $cartItems = CartItem::factory()
            ->count(3)
            ->for($cart)
            ->sequence(
                ['product_id' => $products[0]->id],
                ['product_id' => $products[1]->id],
                ['product_id' => $products[2]->id],
            )
            ->create();

        BacktestedCartItem::query()->create([
            'backtest_id' => $backtest->id,
            'backtested_cart_id' => $backtestedCart->id,
            'cart_item_id' => $cartItems[0]->id,
            'product_id' => $products[0]->id,
            'price' => 500,
            'price_currency' => 'GBP',
            'offer_price' => 400,
            'offer_price_currency' => 'GBP',
        ]);

        BacktestedCartItem::query()->create([
            'backtest_id' => $backtest->id,
            'backtested_cart_id' => $backtestedCart->id,
            'cart_item_id' => $cartItems[1]->id,
            'product_id' => $products[1]->id,
            'price' => 500,
            'price_currency' => 'GBP',
            'offer_price' => 350,
            'offer_price_currency' => 'GBP',
        ]);

        BacktestedCartItem::query()->create([
            'backtest_id' => $backtest->id,
            'backtested_cart_id' => $backtestedCart->id,
            'cart_item_id' => $cartItems[2]->id,
            'product_id' => $products[2]->id,
            'price' => 500,
            'price_currency' => 'GBP',
            'offer_price' => 500,
            'offer_price_currency' => 'GBP',
        ]);

        Livewire::test(ViewBacktestedCart::class, [
            'record' => $backtestedCart->ulid,
        ])
            ->assertSee((string) $cart->ulid)
            ->assertSee('End-to-end')
            ->assertSee('900 ns')
            ->assertSee('Solve')
            ->assertSee('800 ns')
            ->assertSee('Subtotal')
            ->assertSee('Discount')
            ->assertSee('Total')
            ->assertSee('£10.00')
            ->assertSee('£2.50')
            ->assertSee('£7.50')
            ->assertSee('2/3');
    },
);

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
            'processing_time' => 900,
            'solve_time' => 800,
        ]);

        $product = Product::factory()->for($this->team)->create();
        $product->syncTags(['meal-deal:main']);
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
            ->assertSee('Tags')
            ->assertSee('meal-deal:main')
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
            ->assertSee((string) $cart->ulid)
            ->assertSee('End-to-end')
            ->assertSee('Solve')
            ->assertSee('Promotion(s)')
            ->assertSee('Backtest Promo');
    },
);

it(
    'shows end-to-end and solve percentile stats with human-readable units',
    function (): void {
        $stack = PromotionStack::factory()->for($this->team)->create();
        $backtest = Backtest::query()->create([
            'promotion_stack_id' => $stack->id,
            'total_carts' => 10,
            'processed_carts' => 10,
            'status' => BacktestStatus::Completed,
        ]);

        foreach (
            [100, 200, 300, 400, 500, 600, 700, 800, 900, 1000] as $index => $processingTime
        ) {
            $cart = Cart::factory()->for($this->team)->create();

            BacktestedCart::query()->create([
                'backtest_id' => $backtest->id,
                'cart_id' => $cart->id,
                'team_id' => $this->team->id,
                'subtotal' => 1000,
                'subtotal_currency' => 'GBP',
                'total' => 900,
                'total_currency' => 'GBP',
                'processing_time' => $processingTime,
                'solve_time' => ($index + 1) * 50,
            ]);
        }

        Livewire::test(BacktestStatsWidget::class, ['record' => $backtest])
            ->assertSee('End-to-end: P50')
            ->assertSee('500 ns')
            ->assertSee('End-to-end: P90')
            ->assertSee('900 ns')
            ->assertSee('Solve: P50')
            ->assertSee('250 ns')
            ->assertSee('Solve: P90')
            ->assertSee('450 ns');
    },
);
