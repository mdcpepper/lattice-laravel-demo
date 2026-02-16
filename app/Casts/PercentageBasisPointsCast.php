<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<mixed, mixed>
 */
class PercentageBasisPointsCast implements CastsAttributes
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function get(
        Model $model,
        string $key,
        mixed $value,
        array $attributes,
    ): ?float {
        if (is_null($value)) {
            return null;
        }

        return (float) $value / 100;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function set(
        Model $model,
        string $key,
        mixed $value,
        array $attributes,
    ): ?int {
        if (is_null($value) || $value === "") {
            return null;
        }

        return (int) round(((float) $value) * 100);
    }
}
