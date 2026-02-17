<?php

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\Carts\Pages\CreateCart;
use App\Filament\Admin\Resources\Carts\Pages\EditCart;
use App\Filament\Admin\Resources\Carts\Pages\ManageCarts;
use App\Filament\Admin\Resources\Carts\Pages\ViewCart;
use App\Filament\Admin\Resources\Carts\RelationManagers\ItemsRelationManager;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
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

    Livewire::test(ManageCarts::class)
        ->assertCanSeeTableRecords($carts);
});

it('shows customer name and email in the table', function (): void {
    $customer = Customer::factory()->for($this->team)->create();
    Cart::factory()->forCustomer($customer)->create();
    Cart::factory()->anonymous()->for($this->team)->create(['email' => 'guest@example.com']);

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

    expect($cart)->not->toBeNull()
        ->and($cart->customer_id)->toBeNull();
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
            ->exists()
    )->toBeTrue();
});

it('validates that email is a valid email address on create', function (): void {
    Livewire::test(CreateCart::class)
        ->fillForm(['email' => 'not-an-email'])
        ->call('create')
        ->assertHasFormErrors(['email' => 'email']);
});

// ── View page ─────────────────────────────────────────────────────────────────

it('can render the view page', function (): void {
    $cart = Cart::factory()->for($this->team)->create();

    Livewire::test(ViewCart::class, ['record' => $cart->ulid])
        ->assertSuccessful();
});

it('shows the edit header action on the view page', function (): void {
    $cart = Cart::factory()->for($this->team)->create();

    Livewire::test(ViewCart::class, ['record' => $cart->ulid])
        ->assertActionExists('edit');
});

// ── Edit page ─────────────────────────────────────────────────────────────────

it('can render the edit page', function (): void {
    $cart = Cart::factory()->for($this->team)->create();

    Livewire::test(EditCart::class, ['record' => $cart->ulid])
        ->assertSuccessful();
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

// ── Items relation manager ────────────────────────────────────────────────────

it('can see cart items in the relation manager', function (): void {
    $cart = Cart::factory()->for($this->team)->create();
    $items = CartItem::factory()->count(3)->for($cart)->create();

    Livewire::test(ItemsRelationManager::class, [
        'ownerRecord' => $cart,
        'pageClass' => EditCart::class,
    ])->assertCanSeeTableRecords($items);
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
            ->exists()
    )->toBeTrue();
});

it('can add multiple items to the cart at once', function (): void {
    $cart = Cart::factory()->for($this->team)->create();
    $products = Product::factory()->count(3)->for($this->team)->create();

    Livewire::test(ItemsRelationManager::class, [
        'ownerRecord' => $cart,
        'pageClass' => EditCart::class,
    ])
        ->callTableAction('addItem', data: ['product_id' => $products->pluck('id')->all()])
        ->assertHasNoTableActionErrors();

    foreach ($products as $product) {
        expect(
            CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->exists()
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
