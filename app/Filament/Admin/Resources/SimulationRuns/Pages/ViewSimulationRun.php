<?php

namespace App\Filament\Admin\Resources\SimulationRuns\Pages;

use App\Filament\Admin\Resources\SimulationRuns\SimulationRunResource;
use App\Filament\Admin\Resources\SimulationRuns\Widgets\CartSavingDistributionChartWidget;
use App\Filament\Admin\Resources\SimulationRuns\Widgets\DiscountDistributionChartWidget;
use App\Filament\Admin\Resources\SimulationRuns\Widgets\SimulationRunStatsWidget;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends ViewRecord<Model>
 */
class ViewSimulationRun extends ViewRecord
{
    protected static string $resource = SimulationRunResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            SimulationRunStatsWidget::class,
            DiscountDistributionChartWidget::class,
            CartSavingDistributionChartWidget::class,
        ];
    }
}
