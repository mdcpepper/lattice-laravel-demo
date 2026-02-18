<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\NanosecondDurationFormatter;

test(
    'formats nanosecond durations using a human-readable unit scale',
    function (int|float|null $nanoseconds, string $expected): void {
        expect(NanosecondDurationFormatter::format($nanoseconds))->toBe(
            $expected,
        );
    },
)->with([
    'null values' => [null, 'n/a'],
    'nanoseconds' => [999, '999 ns'],
    'microseconds' => [1_000, '1.00 μs'],
    'milliseconds' => [1_234_567, '1.23 ms'],
]);
