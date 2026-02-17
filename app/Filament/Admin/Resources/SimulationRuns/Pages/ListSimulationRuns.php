<?php

namespace App\Filament\Admin\Resources\SimulationRuns\Pages;

use App\Filament\Admin\Resources\SimulationRuns\SimulationRunResource;
use Filament\Resources\Pages\ListRecords;

class ListSimulationRuns extends ListRecords
{
    protected static string $resource = SimulationRunResource::class;
}
