<?php

namespace App\Enums;

enum PromotionLayerOutputTargetMode: string
{
    case PassThrough = 'pass_through';
    case Layer = 'layer';

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
            self::Layer => 'Layer',
        };
    }
}
