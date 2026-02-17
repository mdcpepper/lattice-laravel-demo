<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

trait HasRouteUlid
{
    use HasUlids;

    /**
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'ulid';
    }

    /**
     * @return string[]
     */
    public function uniqueIds()
    {
        return ['ulid'];
    }
}
