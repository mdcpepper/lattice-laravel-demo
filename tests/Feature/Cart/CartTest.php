<?php

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\PromotionStack;
use App\Models\Team;

test(
    'it groups sidebar cart items by product and offer price',
    function (): void {
        $team = Team::factory()->create();
        $category = Category::factory()->for($team)->create();
        $product = Product::factory()->for($team)->for($category)->create();

        $cart = Cart::factory()
            ->for($team)
            ->create([
                'subtotal' => 750,
                'total' => 650,
            ]);

        $firstItem = CartItem::factory()
            ->for($cart)
            ->for($product)
            ->create([
                'price' => 250,
                'offer_price' => 200,
            ]);

        $secondItem = CartItem::factory()
            ->for($cart)
            ->for($product)
            ->create([
                'price' => 250,
                'offer_price' => 200,
            ]);

        $differentOfferItem = CartItem::factory()
            ->for($cart)
            ->for($product)
            ->create([
                'price' => 250,
                'offer_price' => 250,
            ]);

        $response = $this->withSession(['cart_ulid' => $cart->ulid])->get('/');

        $response->assertSuccessful();

        $content = $response->getContent();

        expect($content)
            ->not->toBeFalse()
            ->and(substr_count($content, 'class="cart-sidebar-item"'))
            ->toBe(2);

        $response->assertSeeText('× 2');
        $response->assertSeeText('× 1');
        $response->assertSeeText('£2.00');
        $response->assertSee('<del>£2.50</del>', escape: false);
        $response->assertSeeText('£2.50');
        $response->assertDontSee('<del>£2.00</del>', escape: false);

        $contentAsString = (string) $content;

        expect(
            str_contains(
                $contentAsString,
                "formaction=\"/cart/items/{$firstItem->id}/remove\"",
            ) ||
                str_contains(
                    $contentAsString,
                    "formaction=\"/cart/items/{$secondItem->id}/remove\"",
                ),
        )
            ->toBeTrue()
            ->and(
                str_contains(
                    $contentAsString,
                    "formaction=\"/cart/items/{$differentOfferItem->id}/remove\"",
                ),
            )
            ->toBeTrue()
            ->and(substr_count($contentAsString, 'formaction="/cart/items/'))
            ->toBe(2);
    },
);

test(
    'it groups same product lines with identical offer prices in the sidebar',
    function (): void {
        $team = Team::factory()->create();
        $category = Category::factory()->for($team)->create();
        $product = Product::factory()
            ->for($team)
            ->for($category)
            ->create([
                'name' => 'Soft Drinks',
            ]);

        $cart = Cart::factory()
            ->for($team)
            ->create([
                'subtotal' => 597,
                'total' => 398,
            ]);

        CartItem::factory()
            ->for($cart)
            ->for($product)
            ->create([
                'price' => 199,
                'offer_price' => 199,
            ]);

        CartItem::factory()
            ->for($cart)
            ->for($product)
            ->create([
                'price' => 199,
                'offer_price' => 199,
            ]);

        CartItem::factory()
            ->for($cart)
            ->for($product)
            ->create([
                'price' => 199,
                'offer_price' => 0,
            ]);

        $response = $this->withSession(['cart_ulid' => $cart->ulid])->get('/');

        $response->assertSuccessful();

        $content = (string) $response->getContent();

        expect(substr_count($content, 'class="cart-sidebar-item"'))->toBe(2);

        $response->assertSeeText('Soft Drinks');
        $response->assertSeeText('× 2');
        $response->assertSeeText('× 1');
        $response->assertSeeText('£1.99');
        $response->assertSeeText('£0.00');
        $response->assertSee('<del>£1.99</del>', escape: false);
    },
);

test(
    'it shows per-item promotion labels on discounted items',
    function (): void {
        $team = Team::factory()->create();

        $category = Category::factory()->for($team)->create();
        $product = Product::factory()
            ->for($team)
            ->for($category)
            ->create(['name' => 'Sandwich']);
        $fullPriceProduct = Product::factory()
            ->for($team)
            ->for($category)
            ->create(['name' => 'Water']);

        $cart = Cart::factory()
            ->for($team)
            ->create(['subtotal' => 500, 'total' => 400]);
        $stack = PromotionStack::factory()->for($team)->create();
        $promotion = Promotion::factory()
            ->for($team)
            ->create(['name' => 'Summer Sale']);

        $discountedItem = CartItem::factory()
            ->for($cart)
            ->for($product)
            ->create(['price' => 300, 'offer_price' => 200]);
        $fullPriceItem = CartItem::factory()
            ->for($cart)
            ->for($fullPriceProduct)
            ->create(['price' => 200, 'offer_price' => 200]);

        PromotionRedemption::factory()->create([
            'promotion_id' => $promotion->id,
            'promotion_stack_id' => $stack->id,
            'redeemable_type' => $discountedItem->getMorphClass(),
            'redeemable_id' => $discountedItem->id,
            'sort_order' => 0,
            'redemption_idx' => 0,
            'original_price' => 300,
            'original_price_currency' => 'GBP',
            'final_price' => 200,
            'final_price_currency' => 'GBP',
        ]);

        $response = $this->withSession(['cart_ulid' => $cart->ulid])->get('/');

        $response->assertSuccessful();
        $response->assertSee(
            'class="cart-sidebar-item-promotions"',
            escape: false,
        );
        $response->assertSee(
            'class="cart-sidebar-item-promotion"',
            escape: false,
        );
        $response->assertSeeText('Summer Sale');

        $content = (string) $response->getContent();
        expect(
            substr_count($content, 'class="cart-sidebar-item-promotions"'),
        )->toBe(1);
    },
);

test(
    'it shows a savings breakdown between subtotal and total',
    function (): void {
        $team = Team::factory()->create();

        $category = Category::factory()->for($team)->create();
        $productA = Product::factory()
            ->for($team)
            ->for($category)
            ->create(['name' => 'Cola']);
        $productB = Product::factory()
            ->for($team)
            ->for($category)
            ->create(['name' => 'Crisps']);

        $cart = Cart::factory()
            ->for($team)
            ->create(['subtotal' => 600, 'total' => 400]);
        $stack = PromotionStack::factory()->for($team)->create();
        $promotionA = Promotion::factory()
            ->for($team)
            ->create(['name' => 'Meal Deal']);
        $promotionB = Promotion::factory()
            ->for($team)
            ->create(['name' => 'Weekend Offer']);

        $itemA = CartItem::factory()
            ->for($cart)
            ->for($productA)
            ->create(['price' => 300, 'offer_price' => 200]);
        $itemB = CartItem::factory()
            ->for($cart)
            ->for($productB)
            ->create(['price' => 300, 'offer_price' => 200]);

        PromotionRedemption::factory()->create([
            'promotion_id' => $promotionA->id,
            'promotion_stack_id' => $stack->id,
            'redeemable_type' => $itemA->getMorphClass(),
            'redeemable_id' => $itemA->id,
            'sort_order' => 0,
            'redemption_idx' => 0,
            'original_price' => 300,
            'original_price_currency' => 'GBP',
            'final_price' => 200,
            'final_price_currency' => 'GBP',
        ]);

        PromotionRedemption::factory()->create([
            'promotion_id' => $promotionB->id,
            'promotion_stack_id' => $stack->id,
            'redeemable_type' => $itemB->getMorphClass(),
            'redeemable_id' => $itemB->id,
            'sort_order' => 0,
            'redemption_idx' => 0,
            'original_price' => 300,
            'original_price_currency' => 'GBP',
            'final_price' => 200,
            'final_price_currency' => 'GBP',
        ]);

        $response = $this->withSession(['cart_ulid' => $cart->ulid])->get('/');

        $response->assertSuccessful();
        $response->assertSeeText('Savings');
        $response->assertSeeText('Meal Deal');
        $response->assertSeeText('Weekend Offer');
        $response->assertSeeText('× 1');
        $response->assertSeeText('(1 item)');
        $response->assertSeeText('Total');
        $response->assertSeeText('£4.00');
        $response->assertSee('class="cart-sidebar-row savings"', escape: false);
        $response->assertSee(
            'class="cart-sidebar-row savings-detail"',
            escape: false,
        );
    },
);

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
