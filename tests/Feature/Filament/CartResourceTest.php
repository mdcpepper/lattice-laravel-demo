<?php

namespace Tests\Feature\Filament;

use App\Enums\SimpleDiscountKind;
use App\Events\CartRecalculationRequested;
use App\Filament\Admin\Resources\Carts\Pages\CreateCart;
use App\Filament\Admin\Resources\Carts\Pages\EditCart;
use App\Filament\Admin\Resources\Carts\Pages\ManageCarts;
use App\Filament\Admin\Resources\Carts\Pages\ViewCart;
use App\Filament\Admin\Resources\Carts\RelationManagers\ItemsRelationManager;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Promotions\DirectDiscountPromotion;
use App\Models\Promotions\Promotion;
use App\Models\Promotions\PromotionRedemption;
use App\Models\Promotions\PromotionStack;
use App\Models\Promotions\SimpleDiscount;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function (): void {
    $user = User::factory()->create();
    $this->team = Team::factory()->create();

    $this->team->members()->attach($user);

    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->team, isQuiet: true);

    $this->actingAs($user);
});

// ── List page ─────────────────────────────────────────────────────────────────

it('can render the list page', function (): void {
    Livewire::test(ManageCarts::class)->assertSuccessful();
});

it('can see carts in the table', function (): void {
    $carts = Cart::factory()->count(3)->for($this->team)->create();

    Livewire::test(ManageCarts::class)->assertCanSeeTableRecords($carts);
});

it('shows customer name and email in the table', function (): void {
    $customer = Customer::factory()->for($this->team)->create();

    Cart::factory()->forCustomer($customer)->create();

    Cart::factory()
        ->anonymous()
        ->for($this->team)
        ->create(['email' => 'guest@example.com']);

    Livewire::test(ManageCarts::class)
        ->assertSee($customer->name)
        ->assertSee('guest@example.com');
});

it('shows view and edit actions per row', function (): void {
    $cart = Cart::factory()->for($this->team)->create();

    Livewire::test(ManageCarts::class)
        ->assertTableActionExists('view', record: $cart)
        ->assertTableActionExists('edit', record: $cart);
});

it(
    'shows subtotal, total, and discount columns in the carts table',
    function (): void {
        Cart::factory()
            ->for($this->team)
            ->create([
                'subtotal' => 1_000,
                'total' => 800,
            ]);

        Livewire::test(ManageCarts::class)
            ->assertSee('Subtotal')
            ->assertSee('Total')
            ->assertSee('Discount');
    },
);

it('renders cart savings in major currency units', function (): void {
    Cart::factory()
        ->for($this->team)
        ->create([
            'subtotal' => 3_98,
            'total' => 1_99,
            'subtotal_currency' => 'GBP',
            'total_currency' => 'GBP',
        ]);

    Livewire::test(ManageCarts::class)
        ->assertSee('£3.98')
        ->assertSee('£1.99')
        ->assertDontSee('£199.00');
});

// ── Create page ───────────────────────────────────────────────────────────────

it('can render the create page', function (): void {
    Livewire::test(CreateCart::class)->assertSuccessful();
});

it('can create a cart with an email', function (): void {
    Livewire::test(CreateCart::class)
        ->fillForm([
            'email' => 'shopper@example.com',
            'customer_id' => null,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $cart = Cart::query()
        ->where('team_id', $this->team->id)
        ->where('email', 'shopper@example.com')
        ->first();

    expect($cart)->not->toBeNull()->and($cart->customer_id)->toBeNull();
});

it('can create a cart for a customer', function (): void {
    $customer = Customer::factory()->for($this->team)->create();

    Livewire::test(CreateCart::class)
        ->fillForm(['customer_id' => $customer->id])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(
        Cart::query()
            ->where('team_id', $this->team->id)
            ->where('customer_id', $customer->id)
            ->exists(),
    )->toBeTrue();
});

it(
    'validates that email is a valid email address on create',
    function (): void {
        Livewire::test(CreateCart::class)
            ->fillForm(['email' => 'not-an-email'])
            ->call('create')
            ->assertHasFormErrors(['email' => 'email']);
    },
);

// ── View page ─────────────────────────────────────────────────────────────────

it('can render the view page', function (): void {
    $cart = Cart::factory()->for($this->team)->create();

    Livewire::test(ViewCart::class, [
        'record' => $cart->ulid,
    ])->assertSuccessful();
});

it('shows the edit header action on the view page', function (): void {
    $cart = Cart::factory()->for($this->team)->create();

    Livewire::test(ViewCart::class, [
        'record' => $cart->ulid,
    ])->assertActionExists('edit');
});

it('shows cart summary stats on the view page', function (): void {
    $cart = Cart::factory()
        ->for($this->team)
        ->create([
            'subtotal' => 1_000,
            'total' => 750,
        ]);

    $products = Product::factory()
        ->count(3)
        ->for($this->team)
        ->create([
            'price' => 500,
        ]);

    CartItem::factory()
        ->for($cart)
        ->for($products[0])
        ->create([
            'price' => 500,
            'offer_price' => 400,
        ]);

    CartItem::factory()
        ->for($cart)
        ->for($products[1])
        ->create([
            'price' => 500,
            'offer_price' => 350,
        ]);

    CartItem::factory()
        ->for($cart)
        ->for($products[2])
        ->create([
            'price' => 500,
            'offer_price' => 500,
        ]);

    Livewire::test(ViewCart::class, [
        'record' => $cart->ulid,
    ])
        ->assertSee('Subtotal')
        ->assertSee('Discount')
        ->assertSee('Total')
        ->assertSee('£10.00')
        ->assertSee('£2.50')
        ->assertSee('£7.50')
        ->assertSee('2/3');
});

// ── Edit page ─────────────────────────────────────────────────────────────────

it('can render the edit page', function (): void {
    $cart = Cart::factory()->for($this->team)->create();

    Livewire::test(EditCart::class, [
        'record' => $cart->ulid,
    ])->assertSuccessful();
});

it('can update the email on a cart', function (): void {
    $cart = Cart::factory()->anonymous()->for($this->team)->create();

    Livewire::test(EditCart::class, ['record' => $cart->ulid])
        ->fillForm(['email' => 'updated@example.com'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($cart->fresh()->email)->toBe('updated@example.com');
});

it('can assign a customer to a cart', function (): void {
    $cart = Cart::factory()->anonymous()->for($this->team)->create();
    $customer = Customer::factory()->for($this->team)->create();

    Livewire::test(EditCart::class, ['record' => $cart->ulid])
        ->fillForm(['customer_id' => $customer->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($cart->fresh()->customer_id)->toBe($customer->id);
});

it('validates email on the edit page', function (): void {
    $cart = Cart::factory()->for($this->team)->create();

    Livewire::test(EditCart::class, ['record' => $cart->ulid])
        ->fillForm(['email' => 'bad-email'])
        ->call('save')
        ->assertHasFormErrors(['email' => 'email']);
});

it(
    'dispatches recalculation when the promotion stack assignment changes',
    function (): void {
        Event::fake();

        $cart = Cart::factory()->for($this->team)->create();
        $stack = PromotionStack::factory()->for($this->team)->create();

        Livewire::test(EditCart::class, ['record' => $cart->ulid])
            ->fillForm(['promotion_stack_id' => $stack->id])
            ->call('save')
            ->assertHasNoFormErrors();

        Event::assertDispatched(
            CartRecalculationRequested::class,
            fn (CartRecalculationRequested $event): bool => $event->cartId ===
                $cart->id,
        );
    },
);

// ── Items relation manager ────────────────────────────────────────────────────

it('can see cart items in the relation manager', function (): void {
    $cart = Cart::factory()->for($this->team)->create();
    $items = CartItem::factory()->count(3)->for($cart)->create();

    Livewire::test(ItemsRelationManager::class, [
        'ownerRecord' => $cart,
        'pageClass' => EditCart::class,
    ])->assertCanSeeTableRecords($items);
});

it(
    'shows price, offer price, and discount columns in the items relation manager',
    function (): void {
        $cart = Cart::factory()->for($this->team)->create();
        CartItem::factory()
            ->for($cart)
            ->create([
                'price' => 1_000,
                'offer_price' => 800,
            ]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $cart,
            'pageClass' => EditCart::class,
        ])
            ->assertSee('Price')
            ->assertSee('Offer Price')
            ->assertSee('Discount');
    },
);

it(
    'shows unit price and promotion name in the items relation manager',
    function (): void {
        $cart = Cart::factory()->for($this->team)->create();
        $product = Product::factory()
            ->for($this->team)
            ->create(['name' => 'Soft Drinks', 'price' => 1_99]);
        $item = CartItem::factory()
            ->for($cart)
            ->for($product)
            ->create([
                'price' => 199,
                'offer_price' => 199,
            ]);

        $discount = SimpleDiscount::query()->create([
            'kind' => SimpleDiscountKind::PercentageOff,
            'percentage' => 10,
        ]);
        $directDiscount = DirectDiscountPromotion::query()->create([
            'simple_discount_id' => $discount->id,
        ]);
        $promotion = Promotion::query()->create([
            'team_id' => $this->team->id,
            'name' => 'Soft Drink 10% Off',
            'promotionable_type' => $directDiscount->getMorphClass(),
            'promotionable_id' => $directDiscount->id,
        ]);

        $stack = PromotionStack::factory()->for($this->team)->create();

        PromotionRedemption::query()->create([
            'promotion_id' => $promotion->id,
            'promotion_stack_id' => $stack->id,
            'redeemable_type' => CartItem::class,
            'redeemable_id' => $item->id,
            'sort_order' => 0,
            'original_price' => 199,
            'original_price_currency' => 'GBP',
            'final_price' => 179,
            'final_price_currency' => 'GBP',
        ]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $cart,
            'pageClass' => EditCart::class,
        ])
            ->assertSee('Price')
            ->assertSee('Promotion')
            ->assertSee('Soft Drink 10% Off');
    },
);

it('renders item savings in major currency units', function (): void {
    $cart = Cart::factory()->for($this->team)->create();
    $product = Product::factory()
        ->for($this->team)
        ->create(['price' => 3_98]);

    CartItem::factory()
        ->for($cart)
        ->for($product)
        ->create([
            'price' => 3_98,
            'offer_price' => 1_99,
            'price_currency' => 'GBP',
            'offer_price_currency' => 'GBP',
        ]);

    Livewire::test(ItemsRelationManager::class, [
        'ownerRecord' => $cart,
        'pageClass' => EditCart::class,
    ])
        ->assertSee('£3.98')
        ->assertSee('£1.99')
        ->assertDontSee('£199.00');
});

it('does not show items from other carts', function (): void {
    $cart = Cart::factory()->for($this->team)->create();
    $otherItem = CartItem::factory()->create();

    Livewire::test(ItemsRelationManager::class, [
        'ownerRecord' => $cart,
        'pageClass' => EditCart::class,
    ])->assertCanNotSeeTableRecords([$otherItem]);
});

it('can add a single item to the cart', function (): void {
    $cart = Cart::factory()->for($this->team)->create();
    $product = Product::factory()->for($this->team)->create();

    Livewire::test(ItemsRelationManager::class, [
        'ownerRecord' => $cart,
        'pageClass' => EditCart::class,
    ])
        ->callTableAction('addItem', data: ['product_id' => [$product->id]])
        ->assertHasNoTableActionErrors();

    expect(
        CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->exists(),
    )->toBeTrue();
});

it('can add multiple items to the cart at once', function (): void {
    $cart = Cart::factory()->for($this->team)->create();
    $products = Product::factory()->count(3)->for($this->team)->create();

    Livewire::test(ItemsRelationManager::class, [
        'ownerRecord' => $cart,
        'pageClass' => EditCart::class,
    ])
        ->callTableAction(
            'addItem',
            data: ['product_id' => $products->pluck('id')->all()],
        )
        ->assertHasNoTableActionErrors();

    foreach ($products as $product) {
        expect(
            CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->exists(),
        )->toBeTrue();
    }
});

it('requires at least one product when adding items', function (): void {
    $cart = Cart::factory()->for($this->team)->create();

    Livewire::test(ItemsRelationManager::class, [
        'ownerRecord' => $cart,
        'pageClass' => EditCart::class,
    ])
        ->callTableAction('addItem', data: ['product_id' => null])
        ->assertHasTableActionErrors(['product_id' => 'required']);
});

it('can soft-delete (remove) a cart item', function (): void {
    $cart = Cart::factory()->for($this->team)->create();
    $item = CartItem::factory()->for($cart)->create();

    Livewire::test(ItemsRelationManager::class, [
        'ownerRecord' => $cart,
        'pageClass' => EditCart::class,
    ])
        ->callTableAction('delete', record: $item)
        ->assertHasNoTableActionErrors();

    expect($item->fresh()->deleted_at)->not->toBeNull();
});

it('can restore a soft-deleted cart item', function (): void {
    $cart = Cart::factory()->for($this->team)->create();
    $item = CartItem::factory()->for($cart)->create();
    $item->delete();

    Livewire::test(ItemsRelationManager::class, [
        'ownerRecord' => $cart,
        'pageClass' => EditCart::class,
    ])
        ->callTableAction('restore', record: $item)
        ->assertHasNoTableActionErrors();

    expect($item->fresh()->deleted_at)->toBeNull();
});

it('hides soft-deleted items by default', function (): void {
    $cart = Cart::factory()->for($this->team)->create();
    $activeItem = CartItem::factory()->for($cart)->create();
    $deletedItem = CartItem::factory()->for($cart)->create();
    $deletedItem->delete();

    Livewire::test(ItemsRelationManager::class, [
        'ownerRecord' => $cart,
        'pageClass' => EditCart::class,
    ])
        ->assertCanSeeTableRecords([$activeItem])
        ->assertCanNotSeeTableRecords([$deletedItem]);
});
