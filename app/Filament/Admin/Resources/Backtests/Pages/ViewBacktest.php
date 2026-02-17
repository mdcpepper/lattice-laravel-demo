<?php

namespace App\Filament\Admin\Resources\Backtests\Pages;

use App\Filament\Admin\Resources\Backtests\BacktestResource;
use App\Filament\Admin\Resources\Backtests\Widgets\BacktestStatsWidget;
use App\Filament\Admin\Resources\Backtests\Widgets\CartSavingDistributionChartWidget;
use App\Filament\Admin\Resources\Backtests\Widgets\DiscountDistributionChartWidget;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends ViewRecord<Model>
 */
class ViewBacktest extends ViewRecord
{
    protected static string $resource = BacktestResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            BacktestStatsWidget::class,
            DiscountDistributionChartWidget::class,
            CartSavingDistributionChartWidget::class,
        ];
    }
}
