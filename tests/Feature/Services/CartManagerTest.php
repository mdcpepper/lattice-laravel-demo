<?php

namespace Tests\Feature\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Team;
use App\Services\CartManager;

describe('currentCart()', function (): void {
    it('creates a new cart when none exists for the session', function (): void {
        $team = Team::factory()->create();
        $manager = app(CartManager::class);

        $cart = $manager->currentCart($team, 'session-abc');

        expect($cart)->toBeInstanceOf(Cart::class)
            ->and($cart->session_id)->toBe('session-abc')
            ->and($cart->team_id)->toBe($team->id)
            ->and($cart->wasRecentlyCreated)->toBeTrue();
    });

    it('returns the existing cart when one already exists for the session', function (): void {
        $team = Team::factory()->create();
        $existing = Cart::factory()->for($team)->create(['session_id' => 'session-abc']);
        $manager = app(CartManager::class);

        $cart = $manager->currentCart($team, 'session-abc');

        expect($cart->id)->toBe($existing->id)
            ->and($cart->wasRecentlyCreated)->toBeFalse();
    });

    it('creates separate carts for different session IDs', function (): void {
        $team = Team::factory()->create();
        $manager = app(CartManager::class);

        $cartA = $manager->currentCart($team, 'session-a');
        $cartB = $manager->currentCart($team, 'session-b');

        expect($cartA->id)->not->toBe($cartB->id);
    });

    it('creates separate carts for different teams with the same session ID', function (): void {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $manager = app(CartManager::class);

        $cartA = $manager->currentCart($teamA, 'session-shared');
        $cartB = $manager->currentCart($teamB, 'session-shared');

        expect($cartA->id)->not->toBe($cartB->id);
    });
});

describe('addItem()', function (): void {
    it('creates a CartItem for the given product', function (): void {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create();
        $manager = app(CartManager::class);

        $item = $manager->addItem($cart, $product);

        expect($item)->toBeInstanceOf(CartItem::class)
            ->and($item->cart_id)->toBe($cart->id)
            ->and($item->product_id)->toBe($product->id);
    });

    it('creates two rows when the same product is added twice', function (): void {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create();
        $manager = app(CartManager::class);

        $manager->addItem($cart, $product);
        $manager->addItem($cart, $product);

        expect($cart->items()->count())->toBe(2);
    });
});

describe('removeItem()', function (): void {
    it('soft-deletes the item', function (): void {
        $item = CartItem::factory()->create();
        $manager = app(CartManager::class);

        $manager->removeItem($item);

        expect($item->deleted_at)->not->toBeNull()
            ->and(CartItem::query()->find($item->id))->toBeNull()
            ->and(CartItem::withTrashed()->find($item->id))->not->toBeNull();
    });

    it('re-adding after removal creates a fresh row', function (): void {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create();
        $manager = app(CartManager::class);

        $first = $manager->addItem($cart, $product);
        $manager->removeItem($first);

        $second = $manager->addItem($cart, $product);

        expect($second->id)->not->toBe($first->id)
            ->and($second->deleted_at)->toBeNull()
            ->and($cart->items()->count())->toBe(1);
    });
});
