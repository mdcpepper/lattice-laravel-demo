<?php

namespace App\Filament\Admin\Resources\BacktestedCarts\Pages;

use App\Filament\Admin\Resources\BacktestedCarts\BacktestedCartResource;
use App\Filament\Admin\Resources\BacktestedCarts\Widgets\BacktestedCartStatsWidget;
use App\Filament\Admin\Resources\Backtests\BacktestResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends ViewRecord<Model>
 */
class ViewBacktestedCart extends ViewRecord
{
    protected static string $resource = BacktestedCartResource::class;

    protected function getHeaderWidgets(): array
    {
        return [BacktestedCartStatsWidget::class];
    }

    public function getBreadcrumbs(): array
    {
        $backtest = $this->getRecord()->backtest;

        return [
            BacktestResource::getUrl(
                'index',
            ) => BacktestResource::getNavigationLabel(),
            BacktestResource::getUrl('view', [
                'record' => $backtest,
            ]) => $backtest->ulid,
            $this->getBreadcrumb(),
        ];
    }
}
