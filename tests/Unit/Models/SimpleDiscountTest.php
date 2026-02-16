<?php

namespace Tests\Unit\Models;

use App\Models\SimpleDiscount;

test("percentage is stored as basis points", function (): void {
    $discount = new SimpleDiscount();

    $discount->percentage = 10.0;

    expect($discount->getAttributes()["percentage"])->toBe(1000);
    expect($discount->percentage)->toBe(10.0);
});

test(
    "percentage basis points are exposed as decimal percentage",
    function (): void {
        $discount = new SimpleDiscount();

        $discount->setRawAttributes(
            [
                "percentage" => 2550,
            ],
            true,
        );

        expect($discount->percentage)->toBe(25.5);
    },
);

test("percentage accepts null and empty values", function (): void {
    $discount = new SimpleDiscount();

    $discount->percentage = null;
    expect($discount->getAttributes()["percentage"])->toBeNull();

    $discount->percentage = "";
    expect($discount->getAttributes()["percentage"])->toBeNull();
});
