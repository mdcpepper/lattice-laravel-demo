<?php

namespace Tests\Feature\Filament\Backtests;

use App\Enums\BacktestStatus;
use App\Filament\Admin\Resources\Backtests\Pages\ListBacktests;
use App\Models\Backtest;
use App\Models\BacktestedCart;
use App\Models\BacktestedCartItem;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\PromotionStack;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
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
    'shows computed backtest metrics columns on the backtests list page',
    function (): void {
        $stack = PromotionStack::factory()
            ->for($this->team)
            ->create([
                'name' => 'Checkout Stack',
            ]);

        $backtest = Backtest::query()->create([
            'promotion_stack_id' => $stack->id,
            'total_carts' => 2,
            'processed_carts' => 2,
            'status' => BacktestStatus::Completed,
        ]);

        $product = Product::factory()
            ->for($this->team)
            ->create([
                'price' => 1000,
            ]);

        $cartOne = Cart::factory()
            ->for($this->team)
            ->create([
                'promotion_stack_id' => $stack->id,
            ]);
        $cartTwo = Cart::factory()
            ->for($this->team)
            ->create([
                'promotion_stack_id' => $stack->id,
            ]);

        $cartOneItem = CartItem::factory()
            ->for($cartOne)
            ->for($product)
            ->create();
        $cartTwoItem = CartItem::factory()
            ->for($cartTwo)
            ->for($product)
            ->create();

        $backtestedCartOne = BacktestedCart::query()->create([
            'backtest_id' => $backtest->id,
            'cart_id' => $cartOne->id,
            'team_id' => $this->team->id,
            'subtotal' => 1000,
            'subtotal_currency' => 'GBP',
            'total' => 800,
            'total_currency' => 'GBP',
            'processing_time' => 900,
            'solve_time' => 700,
        ]);

        $backtestedCartTwo = BacktestedCart::query()->create([
            'backtest_id' => $backtest->id,
            'cart_id' => $cartTwo->id,
            'team_id' => $this->team->id,
            'subtotal' => 500,
            'subtotal_currency' => 'GBP',
            'total' => 500,
            'total_currency' => 'GBP',
            'processing_time' => 1000,
            'solve_time' => 800,
        ]);

        BacktestedCartItem::query()->create([
            'backtest_id' => $backtest->id,
            'backtested_cart_id' => $backtestedCartOne->id,
            'cart_item_id' => $cartOneItem->id,
            'product_id' => $product->id,
            'price' => 1000,
            'price_currency' => 'GBP',
            'offer_price' => 800,
            'offer_price_currency' => 'GBP',
        ]);

        BacktestedCartItem::query()->create([
            'backtest_id' => $backtest->id,
            'backtested_cart_id' => $backtestedCartTwo->id,
            'cart_item_id' => $cartTwoItem->id,
            'product_id' => $product->id,
            'price' => 500,
            'price_currency' => 'GBP',
            'offer_price' => 500,
            'offer_price_currency' => 'GBP',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(ListBacktests::class)
            ->assertSee('Items discounted')
            ->assertSee('1/2 (50.0%)')
            ->assertSee('Avg. discount per item')
            ->assertSee('£2.00')
            ->assertSee('Avg. saving per cart')
            ->assertSee('£1.00');

        $legacyAggregateQueries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(
                fn (string $query): bool => str_contains(
                    strtolower($query),
                    'sum(case when backtested_cart_items.offer_price < backtested_cart_items.price then 1 else 0 end) as discounted_items',
                ),
            );

        expect($legacyAggregateQueries)->toBeEmpty();

        DB::disableQueryLog();
    },
);
