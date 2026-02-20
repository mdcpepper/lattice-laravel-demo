<?php

namespace Tests\Feature\Services;

use App\Events\CartRecalculationRequested;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Product;
use App\Models\Team;
use App\Services\CartManager;
use App\Services\CurrentTeam;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Event;

describe('currentCart()', function (): void {
    it(
        'creates a new cart and stores its ULID in the session',
        function (): void {
            $team = Team::factory()->create();
            $session = app(Session::class);
            $manager = app(CartManager::class);

            $cart = $manager->currentCart($team, $session);

            expect($cart)
                ->toBeInstanceOf(Cart::class)
                ->and($cart->team_id)
                ->toBe($team->id)
                ->and($cart->wasRecentlyCreated)
                ->toBeTrue()
                ->and($session->get('cart_ulid'))
                ->toBe($cart->ulid);
        },
    );

    it(
        'returns the existing cart when its ULID is already in the session',
        function (): void {
            $team = Team::factory()->create();
            $existing = Cart::factory()->for($team)->create();

            $session = app(Session::class);
            $session->put('cart_ulid', $existing->ulid);

            $manager = app(CartManager::class);

            $cart = $manager->currentCart($team, $session);

            expect($cart->id)
                ->toBe($existing->id)
                ->and($cart->wasRecentlyCreated)
                ->toBeFalse();
        },
    );

    it(
        'creates a new cart when the session ULID belongs to a different team',
        function (): void {
            $teamA = Team::factory()->create();
            $teamB = Team::factory()->create();

            $cartA = Cart::factory()->for($teamA)->create();

            $session = app(Session::class);
            $session->put('cart_ulid', $cartA->ulid);

            $manager = app(CartManager::class);

            $cartB = $manager->currentCart($teamB, $session);

            expect($cartB->id)
                ->not->toBe($cartA->id)
                ->and($cartB->team_id)
                ->toBe($teamB->id)
                ->and($session->get('cart_ulid'))
                ->toBe($cartB->ulid);
        },
    );

    it('can resolve the cart from the current team service', function (): void {
        $team = Team::factory()->create();
        $session = app(Session::class);

        app()->instance(CurrentTeam::class, new CurrentTeam($team));

        $manager = app(CartManager::class);

        $cart = $manager->currentCartForCurrentTeam($session);

        expect($cart)
            ->toBeInstanceOf(Cart::class)
            ->and($cart->team_id)
            ->toBe($team->id)
            ->and($session->get('cart_ulid'))
            ->toBe($cart->ulid);
    });
});

describe('addItem()', function (): void {
    it('creates a CartItem for the given product', function (): void {
        Event::fake();

        $cart = Cart::factory()->create();
        $product = Product::factory()->create();
        $manager = app(CartManager::class);

        $item = $manager->addItem($cart, $product);

        expect($item)
            ->toBeInstanceOf(CartItem::class)
            ->and($item->cart_id)
            ->toBe($cart->id)
            ->and($item->product_id)
            ->toBe($product->id);

        Event::assertDispatched(
            CartRecalculationRequested::class,
            fn (CartRecalculationRequested $event): bool => $event->cartId ===
                $cart->id,
        );
    });

    it(
        'creates two rows when the same product is added twice',
        function (): void {
            Event::fake();

            $cart = Cart::factory()->create();
            $product = Product::factory()->create();

            $manager = app(CartManager::class);
            $manager->addItem($cart, $product);
            $manager->addItem($cart, $product);

            expect($cart->items()->count())->toBe(2);

            Event::assertDispatchedTimes(CartRecalculationRequested::class, 2);
        },
    );

    it(
        'can skip auto-recalculation dispatch when requested',
        function (): void {
            Event::fake();

            $cart = Cart::factory()->create();
            $product = Product::factory()->create();

            $manager = app(CartManager::class);
            $manager->addItem($cart, $product, requestRecalculation: false);

            Event::assertNotDispatched(CartRecalculationRequested::class);
        },
    );
});

describe('removeItem()', function (): void {
    it('soft-deletes the item', function (): void {
        Event::fake();

        $item = CartItem::factory()->create();

        $manager = app(CartManager::class);
        $manager->removeItem($item);

        expect($item->deleted_at)
            ->not->toBeNull()
            ->and(CartItem::query()->find($item->id))
            ->toBeNull()
            ->and(CartItem::withTrashed()->find($item->id))
            ->not->toBeNull();

        Event::assertDispatched(
            CartRecalculationRequested::class,
            fn (CartRecalculationRequested $event): bool => $event->cartId ===
                $item->cart_id,
        );
    });

    it('re-adding after removal creates a fresh row', function (): void {
        Event::fake();

        $cart = Cart::factory()->create();
        $product = Product::factory()->create();

        $manager = app(CartManager::class);
        $first = $manager->addItem($cart, $product);
        $manager->removeItem($first);

        $second = $manager->addItem($cart, $product);

        expect($second->id)
            ->not->toBe($first->id)
            ->and($second->deleted_at)
            ->toBeNull()
            ->and($cart->items()->count())
            ->toBe(1);
    });

    it('can dispatch recalculation manually', function (): void {
        Event::fake();

        $cart = Cart::factory()->create();
        $manager = app(CartManager::class);

        $manager->requestRecalculation($cart);

        Event::assertDispatched(
            CartRecalculationRequested::class,
            fn (CartRecalculationRequested $event): bool => $event->cartId ===
                $cart->id,
        );
    });
});
