<?php

namespace App\Enums;

enum SimpleDiscountKind: string
{
    case PercentageOff = "percentage_off";
    case AmountOverride = "amount_override";
    case AmountOff = "amount_off";

    /**
     * @return array<string, string>
     */
    public static function asSelectOptions(): array
    {
        $options = [];

        foreach (self::cases() as $kind) {
            $options[$kind->value] = $kind->name();
        }

        return $options;
    }

    public function name(): string
    {
        return match ($this) {
            self::PercentageOff => "Percentage Off",
            self::AmountOverride => "Amount Override",
            self::AmountOff => "Amount Off",
        };
    }
}
