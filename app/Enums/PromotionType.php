<?php

namespace App\Enums;

enum PromotionType: string
{
    case DirectDiscount = 'direct_discount';
    case MixAndMatch = 'mix_and_match';
    case PositionalDiscount = 'positional_discount';
    case TieredThreshold = 'tiered_threshold';

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

    public static function hasPromotionQualification(?string $value): bool
    {
        return match ($value) {
            self::DirectDiscount->value,
            self::PositionalDiscount->value => true,
            default => false,
        };
    }

    public function name(): string
    {
        return match ($this) {
            self::DirectDiscount => 'Direct Discount',
            self::MixAndMatch => 'Mix and Match',
            self::PositionalDiscount => 'Positional Discount',
            self::TieredThreshold => 'Tiered Threshold',
        };
    }
}
