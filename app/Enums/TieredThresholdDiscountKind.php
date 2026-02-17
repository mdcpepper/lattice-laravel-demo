<?php

namespace App\Enums;

enum TieredThresholdDiscountKind: string
{
    case PercentageOffEachItem = 'percentage_off_each_item';
    case PercentageOffCheapest = 'percentage_off_cheapest';
    case AmountOffTotal = 'amount_off_total';
    case AmountOffEachItem = 'amount_off_each_item';
    case OverrideTotal = 'override_total';
    case OverrideEachItem = 'override_each_item';
    case OverrideCheapest = 'override_cheapest';

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

    /**
     * @return array<int, int|string>
     */
    public static function percentageTypes(): array
    {
        return [
            self::PercentageOffEachItem->value,
            self::PercentageOffCheapest->value,
        ];
    }

    /**
     * @return array<int, int|string>
     */
    public static function amountTypes(): array
    {
        return [
            self::AmountOffEachItem->value,
            self::AmountOffTotal->value,
            self::OverrideEachItem->value,
            self::OverrideTotal->value,
            self::OverrideCheapest->value,
        ];
    }

    public function name(): string
    {
        return match ($this) {
            self::PercentageOffEachItem => 'Percentage Off Each Item',
            self::PercentageOffCheapest => 'Percentage Off Cheapest',
            self::AmountOffTotal => 'Amount Off Total',
            self::AmountOffEachItem => 'Amount Off Each Item',
            self::OverrideTotal => 'Override Total',
            self::OverrideEachItem => 'Override Each Item',
            self::OverrideCheapest => 'Override Cheapest',
        };
    }
}
