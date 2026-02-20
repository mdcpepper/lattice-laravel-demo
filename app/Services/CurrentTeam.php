<?php

namespace App\Services;

use App\Models\Team;

readonly class CurrentTeam
{
    public function __construct(public Team $team) {}
}
