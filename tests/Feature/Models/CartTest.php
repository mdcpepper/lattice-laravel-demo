<?php

use App\Models\Cart;
use App\Models\Customer;
use App\Models\Team;

it('can be created with a session_id', function (): void {
    $cart = Cart::factory()->create(['session_id' => 'abc-123']);

    expect($cart->session_id)->toBe('abc-123');
});

it('has null email and customer_id by default', function (): void {
    $cart = Cart::factory()->create();

    expect($cart->email)->toBeNull()->and($cart->customer_id)->toBeNull();
});

it('anonymous state sets email and leaves customer_id null', function (): void {
    $cart = Cart::factory()->anonymous()->create();

    expect($cart->email)
        ->toBeString()
        ->not->toBeEmpty()
        ->and($cart->customer_id)
        ->toBeNull();
});

it(
    'forCustomer state sets customer_id and relationship resolves',
    function (): void {
        $team = Team::factory()->create();
        $customer = Customer::factory()->for($team)->create();
        $cart = Cart::factory()->for($team)->forCustomer($customer)->create();

        expect($cart->customer_id)
            ->toBe($customer->id)
            ->and($cart->customer->id)
            ->toBe($customer->id);
    },
);

it('for(Team) sets team_id and relationship resolves', function (): void {
    $team = Team::factory()->create();
    $cart = Cart::factory()->for($team)->create();

    expect($cart->team_id)
        ->toBe($team->id)
        ->and($cart->team->id)
        ->toBe($team->id);
});

it('has a 26-char ULID and getRouteKeyName returns ulid', function (): void {
    $cart = Cart::factory()->create();

    expect($cart->ulid)
        ->toBeString()
        ->toHaveLength(26)
        ->and($cart->getRouteKeyName())
        ->toBe('ulid');
});

it('nullifies customer_id when customer is deleted', function (): void {
    $team = Team::factory()->create();
    $customer = Customer::factory()->for($team)->create();
    $cart = Cart::factory()->for($team)->forCustomer($customer)->create();

    $customer->delete();

    expect($cart->fresh()->customer_id)->toBeNull();
});
