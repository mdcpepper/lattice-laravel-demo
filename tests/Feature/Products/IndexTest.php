<?php

namespace Tests\Feature\Products;

use App\Models\Cart\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotions\PromotionLayer;
use App\Models\Promotions\PromotionStack;
use App\Models\Team;

test('it shows products for a category loaded by slug', function (): void {
    $team = Team::factory()->create();

    $category = Category::factory()
        ->for($team)
        ->create([
            'name' => 'Beverages',
            'slug' => 'beverages',
        ]);

    $firstProduct = Product::factory()
        ->for($team)
        ->for($category)
        ->create([
            'name' => 'Sparkling Water',
            'description' => 'A crisp and refreshing drink. Works well with meals.',
            'price' => 199,
            'thumb_url' => 'https://cdn.example.com/sparkling-water-thumb.jpg',
            'image_url' => 'https://cdn.example.com/sparkling-water.jpg',
        ]);

    $secondProduct = Product::factory()
        ->for($team)
        ->for($category)
        ->create([
            'name' => 'Orange Juice',
            'description' => 'Freshly squeezed citrus flavor.',
            'price' => 349,
            'thumb_url' => 'https://cdn.example.com/orange-juice-thumb.jpg',
            'image_url' => 'https://cdn.example.com/orange-juice.jpg',
        ]);

    $otherCategory = Category::factory()
        ->for($team)
        ->create([
            'name' => 'Snacks',
            'slug' => 'snacks',
        ]);

    Product::factory()
        ->for($team)
        ->for($otherCategory)
        ->create([
            'name' => 'Potato Chips',
            'description' => 'Crunchy and salty.',
        ]);

    $response = $this->get("/{$category->slug}");

    $response->assertSuccessful();

    $response->assertSee('class="site-header-brand"', escape: false);
    $response->assertSeeText(config('app.name'));

    $response->assertSee('aria-label="Breadcrumb"', escape: false);
    $response->assertSee('href="/"', escape: false);
    $response->assertSee(
        "<span aria-current=\"page\">{$category->name}</span>",
        escape: false,
    );

    $response->assertSee('class="with-sidebar"', escape: false);
    $response->assertSee('aria-label="Cart"', escape: false);
    $response->assertSeeText('Your cart is empty.');

    $response->assertSeeText($category->name);
    $response->assertSeeText($firstProduct->name);
    $response->assertSeeText($secondProduct->name);
    $response->assertSeeText('£1.99');
    $response->assertSeeText('£3.49');
    $response->assertSee(
        'class="button button--primary button--add-to-cart"',
        escape: false,
    );
    $response->assertSee('action="/cart/items"', escape: false);
    $response->assertSee('value="Add to cart"', escape: false);
    $response->assertSee('name="_token"', escape: false);
    $response->assertSee('name="product"', escape: false);
    $response->assertSeeText('A crisp and refreshing drink.');
    $response->assertDontSeeText('Works well with meals.');
    $response->assertDontSeeText('Potato Chips');

    $response->assertSee($firstProduct->thumb_url, escape: false);
    $response->assertSee($firstProduct->image_url, escape: false);
    $response->assertSee(
        "srcset=\"{$firstProduct->thumb_url} 300w, {$firstProduct->image_url} 1000w\"",
        escape: false,
    );

    $response->assertSee(
        'sizes="(max-width: 320px) 100vw, 320px"',
        escape: false,
    );
    $response->assertSee('width="300"', escape: false);
    $response->assertSee('height="300"', escape: false);
});

test('it scopes product pages to the default team', function (): void {
    $defaultTeam = Team::factory()->create();

    $defaultCategory = Category::factory()
        ->for($defaultTeam)
        ->create([
            'slug' => 'beverages',
        ]);

    $defaultTeamProduct = Product::factory()
        ->for($defaultTeam)
        ->for($defaultCategory)
        ->create([
            'name' => 'Default Team Product',
        ]);

    $otherTeam = Team::factory()->create();

    $otherCategory = Category::factory()
        ->for($otherTeam)
        ->create([
            'slug' => 'beverages',
        ]);

    $otherTeamProduct = Product::factory()
        ->for($otherTeam)
        ->for($otherCategory)
        ->create([
            'name' => 'Other Team Product',
        ]);

    $response = $this->get('/beverages');

    $response->assertSuccessful();

    $response->assertSeeText($defaultTeamProduct->name);
    $response->assertDontSeeText($otherTeamProduct->name);
});

test('it returns not found for an unknown category slug', function (): void {
    Team::factory()->create();

    $this->get('/does-not-exist')->assertNotFound();
});

test('it shows promotion badges for qualifying products when the cart has a matching stack', function (): void {
    $team = Team::factory()->create();

    $category = Category::factory()
        ->for($team)
        ->create(['slug' => 'beverages']);

    $promotion = createSaleDiscountPromotion($team);

    $stack = PromotionStack::factory()->for($team)->create();
    $layer = PromotionLayer::factory()->for($stack, 'stack')->create();
    $layer->promotions()->attach($promotion->id, ['sort_order' => 0]);

    $cart = Cart::factory()->for($team)->create(['promotion_stack_id' => $stack->id]);

    $qualifyingProduct = Product::factory()
        ->for($team)
        ->for($category)
        ->create(['name' => 'Sparkling Water']);

    $qualifyingProduct->syncTags(['sale']);

    Product::factory()
        ->for($team)
        ->for($category)
        ->create(['name' => 'Still Water']);

    $response = $this->withSession(['cart_ulid' => $cart->ulid])->get("/{$category->slug}");

    $response->assertSuccessful();
    $response->assertSee('class="product-card-promotion-badge"', escape: false);
    $response->assertSeeText($promotion->name);
    $response->assertSee('aria-label="Qualifying promotions"', escape: false);
});

test('it shows no promotion badges when no products qualify', function (): void {
    $team = Team::factory()->create();

    $category = Category::factory()
        ->for($team)
        ->create(['slug' => 'beverages']);

    Product::factory()
        ->for($team)
        ->for($category)
        ->create(['name' => 'Still Water']);

    $response = $this->get("/{$category->slug}");

    $response->assertSuccessful();
    $response->assertDontSee('class="product-card-promotion-badge"', escape: false);
});

test('it shows no promotion badges when the cart has no promotion stack', function (): void {
    $team = Team::factory()->create();

    $category = Category::factory()
        ->for($team)
        ->create(['slug' => 'beverages']);

    $promotion = createSaleDiscountPromotion($team);

    $cart = Cart::factory()->for($team)->create(['promotion_stack_id' => null]);

    $qualifyingProduct = Product::factory()
        ->for($team)
        ->for($category)
        ->create(['name' => 'Sparkling Water']);

    $qualifyingProduct->syncTags(['sale']);

    $response = $this->withSession(['cart_ulid' => $cart->ulid])->get("/{$category->slug}");

    $response->assertSuccessful();
    $response->assertDontSee('class="product-card-promotion-badge"', escape: false);
    $response->assertDontSeeText($promotion->name);
});
