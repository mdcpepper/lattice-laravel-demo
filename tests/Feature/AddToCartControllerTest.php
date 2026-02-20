<?php

namespace Tests\Feature;

use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Team;

test(
    'it adds a product to the current cart for the default team',
    function (): void {
        $defaultTeam = Team::factory()->create();

        $category = Category::factory()
            ->for($defaultTeam)
            ->create([
                'slug' => 'beverages',
            ]);

        $product = Product::factory()
            ->for($defaultTeam)
            ->for($category)
            ->create();

        $response = $this->from("/{$category->slug}")->post(
            route('cart.items.store', absolute: false),
            [
                'product' => $product->id,
            ],
        );

        $response->assertRedirect("/{$category->slug}");

        $cart = Cart::query()->first();

        expect($cart)
            ->not->toBeNull()
            ->and($cart?->team_id)
            ->toBe($defaultTeam->id)
            ->and((string) session('cart_ulid'))
            ->toBe($cart?->ulid);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart?->id,
            'product_id' => $product->id,
        ]);
    },
);

test('it only accepts products from the default team', function (): void {
    $defaultTeam = Team::factory()->create();

    $defaultCategory = Category::factory()
        ->for($defaultTeam)
        ->create([
            'slug' => 'beverages',
        ]);

    $otherTeam = Team::factory()->create();

    $otherCategory = Category::factory()
        ->for($otherTeam)
        ->create([
            'slug' => 'snacks',
        ]);

    $otherTeamProduct = Product::factory()
        ->for($otherTeam)
        ->for($otherCategory)
        ->create();

    $response = $this->from("/{$defaultCategory->slug}")->post(
        route('cart.items.store', absolute: false),
        [
            'product' => $otherTeamProduct->id,
        ],
    );

    $response
        ->assertRedirect("/{$defaultCategory->slug}")
        ->assertSessionHasErrors(['product']);

    $this->assertDatabaseCount('cart_items', 0);
});

test('it removes an item from the current team cart', function (): void {
    $defaultTeam = Team::factory()->create();

    $category = Category::factory()
        ->for($defaultTeam)
        ->create([
            'slug' => 'beverages',
        ]);

    $product = Product::factory()->for($defaultTeam)->for($category)->create();

    $cart = Cart::factory()->for($defaultTeam)->create();
    $item = CartItem::factory()->for($cart)->for($product)->create();

    $response = $this->from("/{$category->slug}")->post(
        route('cart.items.remove', ['item' => $item->id], absolute: false),
    );

    $response->assertRedirect("/{$category->slug}");
    $this->assertSoftDeleted('cart_items', ['id' => $item->id]);
});

test(
    'it returns the cart sidebar HTML when adding a product with an HTMX request',
    function (): void {
        $defaultTeam = Team::factory()->create();

        $category = Category::factory()
            ->for($defaultTeam)
            ->create([
                'slug' => 'beverages',
            ]);

        $product = Product::factory()
            ->for($defaultTeam)
            ->for($category)
            ->create();

        $response = $this->withHeaders(['HX-Request' => 'true'])->post(
            route('cart.items.store', absolute: false),
            [
                'product' => $product->id,
            ],
        );

        $response->assertOk();
        $response->assertSee('cart-sidebar');
    },
);

test(
    'it returns the cart sidebar HTML when removing an item with an HTMX request',
    function (): void {
        $defaultTeam = Team::factory()->create();

        $category = Category::factory()
            ->for($defaultTeam)
            ->create([
                'slug' => 'beverages',
            ]);

        $product = Product::factory()
            ->for($defaultTeam)
            ->for($category)
            ->create();

        $cart = Cart::factory()->for($defaultTeam)->create();
        $item = CartItem::factory()->for($cart)->for($product)->create();

        $response = $this->withHeaders(['HX-Request' => 'true'])->post(
            route('cart.items.remove', ['item' => $item->id], absolute: false),
        );

        $response->assertOk();
        $response->assertSee('cart-sidebar');
    },
);

test('it does not remove cart items from another team', function (): void {
    $defaultTeam = Team::factory()->create();
    $defaultCategory = Category::factory()
        ->for($defaultTeam)
        ->create([
            'slug' => 'beverages',
        ]);

    $otherTeam = Team::factory()->create();
    $otherCategory = Category::factory()
        ->for($otherTeam)
        ->create([
            'slug' => 'snacks',
        ]);
    $otherProduct = Product::factory()
        ->for($otherTeam)
        ->for($otherCategory)
        ->create();
    $otherCart = Cart::factory()->for($otherTeam)->create();
    $otherItem = CartItem::factory()
        ->for($otherCart)
        ->for($otherProduct)
        ->create();

    $response = $this->from("/{$defaultCategory->slug}")->post(
        route('cart.items.remove', ['item' => $otherItem->id], absolute: false),
    );

    $response->assertNotFound();
    $this->assertDatabaseHas('cart_items', ['id' => $otherItem->id]);
});
