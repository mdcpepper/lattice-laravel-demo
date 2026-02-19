<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Services\CurrentTeam;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GetCurrentTeam
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  Closure(): void  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app()->instance(
            CurrentTeam::class,
            new CurrentTeam(Team::query()->firstOrFail()),
        );

        return $next($request);
    }
}
