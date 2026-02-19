<?php

namespace Tests\Feature\Categories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Team;

test('the home page returns a successful response', function (): void {
    $team = Team::factory()->create();

    $categoryWithMainProduct = Category::factory()
        ->for($team)
        ->create([
            'name' => 'Beverages',
            'slug' => 'beverages',
        ]);

    $mainProduct = Product::factory()
        ->for($team)
        ->for($categoryWithMainProduct)
        ->create([
            'name' => 'Sparkling Water',
            'thumb_url' => 'https://cdn.example.com/sparkling-water-thumb.jpg',
            'image_url' => 'https://cdn.example.com/sparkling-water.jpg',
        ]);

    $categoryWithMainProduct->update([
        'main_product_id' => $mainProduct->id,
    ]);

    $categoryWithoutMainProduct = Category::factory()
        ->for($team)
        ->create([
            'name' => 'Snacks',
            'slug' => 'snacks',
        ]);

    Product::factory()
        ->for($team)
        ->for($categoryWithoutMainProduct)
        ->create([
            'price' => 500,
            'thumb_url' => 'https://cdn.example.com/small-snacks-thumb.jpg',
            'image_url' => 'https://cdn.example.com/small-snacks.jpg',
        ]);

    $fallbackProduct = Product::factory()
        ->for($team)
        ->for($categoryWithoutMainProduct)
        ->create([
            'price' => 2000,
            'thumb_url' => 'https://cdn.example.com/large-snacks-thumb.jpg',
            'image_url' => 'https://cdn.example.com/large-snacks.jpg',
        ]);

    $response = $this->get('/');

    $response->assertSuccessful();

    $response->assertSee('class="site-header-brand"', escape: false);
    $response->assertSeeText(config('app.name'));

    $response->assertSee('class="with-sidebar"', escape: false);
    $response->assertSee('aria-label="Cart"', escape: false);
    $response->assertSeeText('Your cart is empty.');

    $response->assertSeeText($categoryWithMainProduct->name);
    $response->assertSeeText($categoryWithoutMainProduct->name);

    $response->assertSee($mainProduct->thumb_url, escape: false);
    $response->assertSee($mainProduct->image_url, escape: false);
    $response->assertSee($fallbackProduct->thumb_url, escape: false);
    $response->assertSee($fallbackProduct->image_url, escape: false);
    $response->assertSee(
        "srcset=\"{$mainProduct->thumb_url} 300w, {$mainProduct->image_url} 1000w\"",
        escape: false,
    );
    $response->assertSee(
        'sizes="(max-width: 320px) 100vw, 320px"',
        escape: false,
    );
    $response->assertSee('width="300"', escape: false);
    $response->assertSee('height="300"', escape: false);
});

test('it scopes home categories to the default team', function (): void {
    $defaultTeam = Team::factory()->create();
    $defaultCategory = Category::factory()
        ->for($defaultTeam)
        ->create([
            'name' => 'Default Team Category',
        ]);

    $otherTeam = Team::factory()->create();
    $otherCategory = Category::factory()
        ->for($otherTeam)
        ->create([
            'name' => 'Other Team Category',
        ]);

    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertSeeText($defaultCategory->name);
    $response->assertDontSeeText($otherCategory->name);
});

test(
    'it lists current cart item product names in the sidebar',
    function (): void {
        $team = Team::factory()->create();
        $category = Category::factory()->for($team)->create();
        $product = Product::factory()
            ->for($team)
            ->for($category)
            ->create([
                'name' => 'Sidebar Cart Product',
            ]);
        $fullPriceProduct = Product::factory()
            ->for($team)
            ->for($category)
            ->create([
                'name' => 'Full Price Sidebar Product',
            ]);

        $cart = Cart::factory()
            ->for($team)
            ->create([
                'subtotal' => 2499,
                'total' => 1999,
            ]);
        $item = CartItem::factory()
            ->for($cart)
            ->for($product)
            ->create([
                'price' => 2499,
                'offer_price' => 1999,
            ]);
        CartItem::factory()
            ->for($cart)
            ->for($fullPriceProduct)
            ->create([
                'price' => 500,
                'offer_price' => 500,
            ]);

        $response = $this->withSession(['cart_ulid' => $cart->ulid])->get('/');

        $response->assertSuccessful();
        $response->assertSee('class="cart-sidebar-items"', escape: false);
        $response->assertSee('class="cart-sidebar-item"', escape: false);
        $response->assertSee('class="cart-sidebar-item-thumb"', escape: false);
        $response->assertSee(
            'class="cart-sidebar-item-heading"',
            escape: false,
        );
        $response->assertSee($product->thumb_url, escape: false);
        $response->assertSee('width="300"', escape: false);
        $response->assertSee('height="300"', escape: false);
        $response->assertSeeText('Sidebar Cart Product');
        $response->assertSeeText('× 1');
        $response->assertSee(
            'class="cart-sidebar-item-pricing"',
            escape: false,
        );
        $response->assertSee('<del>£24.99</del>', escape: false);
        $response->assertSeeText('Full Price Sidebar Product');
        $response->assertSeeText('£5.00');
        $response->assertDontSee('<del>£5.00</del>', escape: false);
        $response->assertSee('value="+"', escape: false);
        $response->assertSee('value="-"', escape: false);
        $response->assertSee('formaction="/cart/items"', escape: false);
        $response->assertSee(
            "formaction=\"/cart/items/{$item->id}/remove\"",
            escape: false,
        );
        $response->assertSeeText('Subtotal');
        $response->assertSeeText('£24.99');
        $response->assertSeeText('Total');
        $response->assertSeeText('£19.99');
        $response->assertDontSeeText('Your cart is empty.');
    },
);
