<?php

namespace Database\Seeders;

use GuzzleHttp\Client;

trait DummyJsonSeeder
{
    protected function makeClient(): Client
    {
        return new Client([
            "base_uri" => "https://dummyjson.com/",
            "timeout" => 10,
        ]);
    }
}
