<?php

namespace Database\Seeders;

use App\Models\Team;
use GuzzleHttp\Client;
use RuntimeException;

trait DummyJsonSeeder
{
    protected function makeClient(): Client
    {
        return new Client([
            'base_uri' => 'https://dummyjson.com/',
            'timeout' => 10,
        ]);
    }

    protected function getDefaultTeam(): Team
    {
        $team = Team::query()
            ->where('name', DatabaseSeeder::DEFAULT_TEAM_NAME)
            ->first();

        if ($team === null) {
            throw new RuntimeException(
                'Default team not found. Run DatabaseSeeder first.',
            );
        }

        return $team;
    }
}
