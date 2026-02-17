<?php

namespace App\Filament\Admin\Resources\SimulatedCarts\Pages;

use App\Filament\Admin\Resources\SimulatedCarts\SimulatedCartResource;
use App\Filament\Admin\Resources\SimulationRuns\SimulationRunResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends ViewRecord<Model>
 */
class ViewSimulatedCart extends ViewRecord
{
    protected static string $resource = SimulatedCartResource::class;

    public function getBreadcrumbs(): array
    {
        $simulationRun = $this->getRecord()->simulationRun;

        return [
            SimulationRunResource::getUrl('index') => SimulationRunResource::getNavigationLabel(),
            SimulationRunResource::getUrl('view', ['record' => $simulationRun]) => $simulationRun->ulid,
            $this->getBreadcrumb(),
        ];
    }
}
