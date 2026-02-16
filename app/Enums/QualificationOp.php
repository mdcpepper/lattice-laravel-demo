<?php

namespace App\Enums;

enum QualificationOp: string
{
    case And = 'and';
    case Or = 'or';

    /**
     * @return array<string, string>
     */
    public static function asSelectOptions(): array
    {
        $options = [];

        foreach (self::cases() as $op) {
            $options[$op->value] = $op->description();
        }

        return $options;
    }

    public function description(): string
    {
        return match ($this) {
            self::And => 'ALL rules must match',
            self::Or => 'ANY rule must match',
        };
    }
}
