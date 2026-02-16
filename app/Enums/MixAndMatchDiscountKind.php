<?php

namespace App\Enums;

enum MixAndMatchDiscountKind: string
{
    case PercentageOffAllItems = 'percentage_off_all_items';
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
     * @return array<int,int|string>
     */
    public static function percentageTypes(): array
    {
        return [
            self::PercentageOffAllItems->value,
            self::PercentageOffCheapest->value,
        ];
    }

    /**
     * @return array<int,int|string>
     */
    public static function amountTypes(): array
    {
        return [
            self::AmountOffEachItem->value,
            self::AmountOffTotal->value,
            self::OverrideEachItem->value,
            self::OverrideTotal->value,
        ];
    }

    public function name(): string
    {
        return match ($this) {
            self::PercentageOffAllItems => 'Percentage Off All Items',
            self::AmountOffEachItem => 'Amount Off Each Item',
            self::OverrideEachItem => 'Override Each Item',
            self::AmountOffTotal => 'Amount Off Total',
            self::OverrideTotal => 'Override Total',
            self::PercentageOffCheapest => 'Percentage Off Cheapest',
            self::OverrideCheapest => 'Override Cheapest',
        };
    }
}
