<?php

namespace App\Filament\Admin\Resources\SimulationRuns;

use App\Filament\Admin\Resources\SimulationRuns\Pages\ListSimulationRuns;
use App\Filament\Admin\Resources\SimulationRuns\Pages\ViewSimulationRun;
use App\Filament\Admin\Resources\SimulationRuns\RelationManagers\SimulatedCartsRelationManager;
use App\Filament\Admin\Resources\SimulationRuns\Tables\SimulationRunsTable;
use App\Filament\Admin\Resources\SimulationRuns\Widgets\CartSavingDistributionChartWidget;
use App\Filament\Admin\Resources\SimulationRuns\Widgets\DiscountDistributionChartWidget;
use App\Filament\Admin\Resources\SimulationRuns\Widgets\SimulationRunStatsWidget;
use App\Models\SimulationRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SimulationRunResource extends Resource
{
    protected static ?string $model = SimulationRun::class;

    protected static bool $isScopedToTenant = false;

    protected static string|UnitEnum|null $navigationGroup = 'Promotions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $navigationLabel = 'Simulation Runs';

    protected static ?string $recordTitleAttribute = 'ulid';

    public static function table(Table $table): Table
    {
        return SimulationRunsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SimulatedCartsRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            SimulationRunStatsWidget::class,
            DiscountDistributionChartWidget::class,
            CartSavingDistributionChartWidget::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSimulationRuns::route('/'),
            'view' => ViewSimulationRun::route('/{record}'),
        ];
    }
}
