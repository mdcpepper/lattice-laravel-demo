<?php

namespace Tests\Unit\Enums;

use App\Enums\SimpleDiscountKind;

test("asSelectOptions", function (): void {
    $expected = [
        SimpleDiscountKind::PercentageOff
            ->value => SimpleDiscountKind::PercentageOff->name(),
        SimpleDiscountKind::AmountOverride
            ->value => SimpleDiscountKind::AmountOverride->name(),
        SimpleDiscountKind::AmountOff
            ->value => SimpleDiscountKind::AmountOff->name(),
    ];

    $actual = SimpleDiscountKind::asSelectOptions();

    expect($actual)->toEqual($expected);
});
