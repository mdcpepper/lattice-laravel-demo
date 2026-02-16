<?php

namespace App\Enums;

enum PromotionType: string
{
    case DirectDiscount = 'direct_discount';
    case MixAndMatch = 'mix_and_match';

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
            self::DirectDiscount->value => true,
            self::MixAndMatch->value => false,
            default => false,
        };
    }

    public function name(): string
    {
        return match ($this) {
            self::DirectDiscount => 'Direct Discount',
            self::MixAndMatch => 'Mix and Match',
        };
    }
}
