<?php

return [
    /*
     |--------------------------------------------------------------------------
     | Laravel money
     |--------------------------------------------------------------------------
     */
    "locale" => config("app.locale", "en_GB"),
    "defaultCurrency" => config("app.currency", "GBP"),
    "defaultFormatter" => null,
    "defaultSerializer" => null,
    "isoCurrenciesPath" => is_dir(__DIR__ . "/../vendor")
        ? __DIR__ . "/../vendor/moneyphp/money/resources/currency.php"
        : __DIR__ . "/../../../moneyphp/money/resources/currency.php",
    "currencies" => [
        "iso" => ["GBP"],
        "custom" => [
            // 'MY1' => 2,
            // 'MY2' => 3
        ],
    ],
];
