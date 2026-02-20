<?php

namespace App\Models\Concerns;

use App\Services\CurrentTeam;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Scope;

trait BelongsToCurrentTeam
{
    /**
     * @param  Scope|Closure|string  $scope
     * @param  Closure|string|null  $implementation
     * @return void
     */
    abstract public static function addGlobalScope(
        $scope,
        $implementation = null,
    );

    protected static function bootBelongsToCurrentTeam(): void
    {
        static::addGlobalScope('currentTeam', function (
            Builder $builder,
        ): void {
            if (! app()->bound(CurrentTeam::class)) {
                return;
            }

            $builder->where(
                $builder->getModel()->qualifyColumn('team_id'),
                app(CurrentTeam::class)->team->id,
            );
        });
    }
}
