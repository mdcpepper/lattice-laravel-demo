<?php

namespace App\Filament\Admin\Resources\Backtests;

use App\Filament\Admin\Resources\Backtests\Pages\ListBacktests;
use App\Filament\Admin\Resources\Backtests\Pages\ViewBacktest;
use App\Filament\Admin\Resources\Backtests\RelationManagers\BacktestedCartsRelationManager;
use App\Filament\Admin\Resources\Backtests\Tables\BacktestsTable;
use App\Filament\Admin\Resources\Backtests\Widgets\BacktestStatsWidget;
use App\Filament\Admin\Resources\Backtests\Widgets\CartSavingDistributionChartWidget;
use App\Filament\Admin\Resources\Backtests\Widgets\DiscountDistributionChartWidget;
use App\Models\Backtest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BacktestResource extends Resource
{
    protected static ?string $model = Backtest::class;

    protected static bool $isScopedToTenant = false;

    protected static string|UnitEnum|null $navigationGroup = 'Promotions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $navigationLabel = 'Backtests';

    protected static ?string $recordTitleAttribute = 'ulid';

    public static function table(Table $table): Table
    {
        return BacktestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [BacktestedCartsRelationManager::class];
    }

    public static function getWidgets(): array
    {
        return [
            BacktestStatsWidget::class,
            DiscountDistributionChartWidget::class,
            CartSavingDistributionChartWidget::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBacktests::route('/'),
            'view' => ViewBacktest::route('/{record}'),
        ];
    }
}
