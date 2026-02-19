<?php

namespace App\Services;

use App\Models\Team;

class CurrentTeam
{
    public function __construct(public readonly Team $team) {}
}
