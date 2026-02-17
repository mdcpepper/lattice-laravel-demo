<?php

namespace App\Enums;

enum PromotionLayerOutputMode: string
{
    case PassThrough = 'pass_through';
    case Split = 'split';

    /**
     * @return array<string, string>
     */
    public static function asSelectOptions(): array
    {
        $options = [];

        foreach (self::cases() as $mode) {
            $options[$mode->value] = $mode->name();
        }

        return $options;
    }

    public function name(): string
    {
        return match ($this) {
            self::PassThrough => 'Pass Through',
            self::Split => 'Split',
        };
    }
}
