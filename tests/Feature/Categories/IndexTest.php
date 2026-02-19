<?php

namespace Tests\Feature\Categories;

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

    $response->assertSee('aria-label="Breadcrumb"', escape: false);
    $response->assertSee(
        '<span aria-current="page">Categories</span>',
        escape: false,
    );

    $response->assertSeeText($categoryWithMainProduct->name);
    $response->assertSeeText($categoryWithoutMainProduct->name);

    $response->assertSee($mainProduct->thumb_url, escape: false);
    $response->assertSee($mainProduct->image_url, escape: false);
    $response->assertSee($fallbackProduct->thumb_url, escape: false);
    $response->assertSee($fallbackProduct->image_url, escape: false);
    $response->assertSee(
        'sizes="(max-width: 320px) 100vw, 320px"',
        escape: false,
    );
});
