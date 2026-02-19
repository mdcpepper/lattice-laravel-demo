<?php

namespace Tests\Feature\Products;

use App\Models\Category;
use App\Models\Product;
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
    $response->assertSee('value="Add to cart"', escape: false);
    $response->assertSee('name="product"', escape: false);
    $response->assertSeeText('A crisp and refreshing drink.');
    $response->assertDontSeeText('Works well with meals.');
    $response->assertDontSeeText('Potato Chips');

    $response->assertSee($firstProduct->thumb_url, escape: false);
    $response->assertSee($firstProduct->image_url, escape: false);

    $response->assertSee(
        'sizes="(max-width: 320px) 100vw, 320px"',
        escape: false,
    );
});

test('it returns not found for an unknown category slug', function (): void {
    $this->get('/does-not-exist')->assertNotFound();
});
