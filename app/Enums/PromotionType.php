<?php

namespace App\Enums;

enum PromotionType: string
{
    case DirectDiscount = "direct_discount";

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
            self::DirectDiscount => "Direct Discount",
        };
    }
}
