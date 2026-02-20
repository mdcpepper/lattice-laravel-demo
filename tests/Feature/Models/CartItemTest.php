<?php

namespace Tests\Feature\Models;

use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Product;

it('adding a product creates a CartItem row', function (): void {
    $cart = Cart::factory()->create();
    $product = Product::factory()->create();

    $item = $cart->items()->create([
        'product_id' => $product->id,
        'price' => 1000,
        'price_currency' => 'GBP',
        'offer_price' => 1000,
        'offer_price_currency' => 'GBP',
    ]);

    expect($item)
        ->toBeInstanceOf(CartItem::class)
        ->and($item->product_id)
        ->toBe($product->id)
        ->and($item->cart_id)
        ->toBe($cart->id);
});

it(
    'adding the same product twice creates two separate rows',
    function (): void {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create();

        $cart->items()->create([
            'product_id' => $product->id,
            'price' => 1000,
            'price_currency' => 'GBP',
            'offer_price' => 1000,
            'offer_price_currency' => 'GBP',
        ]);
        $cart->items()->create([
            'product_id' => $product->id,
            'price' => 1000,
            'price_currency' => 'GBP',
            'offer_price' => 1000,
            'offer_price_currency' => 'GBP',
        ]);

        expect($cart->items()->count())->toBe(2);
    },
);

it('removing an item soft-deletes the row', function (): void {
    $item = CartItem::factory()->create();

    $item->delete();

    expect($item->deleted_at)
        ->not->toBeNull()
        ->and(CartItem::query()->find($item->id))
        ->toBeNull()
        ->and(CartItem::withTrashed()->find($item->id))
        ->not->toBeNull();
});

it('re-adding after remove creates a fresh row', function (): void {
    $cart = Cart::factory()->create();
    $product = Product::factory()->create();

    $first = $cart->items()->create([
        'product_id' => $product->id,
        'price' => 1000,
        'price_currency' => 'GBP',
        'offer_price' => 1000,
        'offer_price_currency' => 'GBP',
    ]);
    $first->delete();

    $second = $cart->items()->create([
        'product_id' => $product->id,
        'price' => 1000,
        'price_currency' => 'GBP',
        'offer_price' => 1000,
        'offer_price_currency' => 'GBP',
    ]);

    expect($second->id)
        ->not->toBe($first->id)
        ->and($second->deleted_at)
        ->toBeNull();
});

it('items() returns only non-deleted rows by default', function (): void {
    $cart = Cart::factory()->create();
    $kept = $cart
        ->items()
        ->create(['product_id' => Product::factory()->create()->id]);
    $removed = $cart
        ->items()
        ->create(['product_id' => Product::factory()->create()->id]);
    $removed->delete();

    $visible = $cart->items()->get();

    expect($visible)
        ->toHaveCount(1)
        ->and($visible->first()->id)
        ->toBe($kept->id);
});

it('items()->withTrashed() returns all rows', function (): void {
    $cart = Cart::factory()->create();
    $cart->items()->create(['product_id' => Product::factory()->create()->id]);
    $removed = $cart
        ->items()
        ->create(['product_id' => Product::factory()->create()->id]);
    $removed->delete();

    expect($cart->items()->withTrashed()->count())->toBe(2);
});

it('has a 26-char ULID and getRouteKeyName returns ulid', function (): void {
    $item = CartItem::factory()->create();

    expect($item->ulid)
        ->toBeString()
        ->toHaveLength(26)
        ->and($item->getRouteKeyName())
        ->toBe('ulid');
});

it('deletes the item when its product is deleted', function (): void {
    $product = Product::factory()->create();
    $item = CartItem::factory()->create(['product_id' => $product->id]);

    $product->delete();

    expect(CartItem::withTrashed()->find($item->id))->toBeNull();
});
