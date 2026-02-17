<?php

namespace App\Filament\Admin\Resources\Backtests\Pages;

use App\Filament\Admin\Resources\Backtests\BacktestResource;
use Filament\Resources\Pages\ListRecords;

class ListBacktests extends ListRecords
{
    protected static string $resource = BacktestResource::class;
}
