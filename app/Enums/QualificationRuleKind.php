<?php

namespace App\Enums;

enum QualificationRuleKind: string
{
    case HasAll = 'has_all';
    case HasAny = 'has_any';
    case HasNone = 'has_none';
    case Group = 'group';

    /**
     * @return array<string, string>
     */
    public static function asSelectOptions(bool $withGroup = true): array
    {
        $options = [];

        foreach (self::cases() as $op) {
            if ($withGroup || $op->value != 'group') {
                $options[$op->value] = $op->description();
            }
        }

        return $options;
    }

    public function description(): string
    {
        return match ($this) {
            self::HasAll => 'Has All',
            self::HasAny => 'Has Any',
            self::HasNone => 'Has None',
            self::Group => 'Group',
        };
    }
}
