<?php

namespace App\Services;

final class NanosecondDurationFormatter
{
    private function __construct() {}

    public static function format(int|float|null $nanoseconds): string
    {
        if (is_null($nanoseconds)) {
            return 'n/a';
        }

        if ($nanoseconds < 1_000) {
            return number_format((int) round((float) $nanoseconds)).' ns';
        }

        if ($nanoseconds < 1_000_000) {
            return number_format(((float) $nanoseconds) / 1_000, 2).' μs';
        }

        return number_format(((float) $nanoseconds) / 1_000_000, 2).' ms';
    }
}
