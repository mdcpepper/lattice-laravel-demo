<?php

namespace App\Filament\Admin\Resources\Backtests\Widgets;

use App\Models\Backtest;
use Filament\Widgets\ChartWidget;

class CartSavingDistributionChartWidget extends ChartWidget
{
    protected ?string $heading = 'Saving Distribution (per cart)';

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '200px';

    public ?Backtest $record = null;

    protected ?string $pollingInterval = '2s';

    protected function getPollingInterval(): ?string
    {
        if ($this->getProgress() == 100) {
            return null;
        }

        return $this->pollingInterval;
    }

    protected function getProgress(): int
    {
        return ($this->record->processed_carts / $this->record->total_carts) *
            100;
    }

    protected function getData(): array
    {
        if (! $this->record instanceof Backtest) {
            return ['datasets' => [], 'labels' => []];
        }

        $buckets = $this->record
            ->backtestedCarts()
            ->whereColumn('total', '<', 'subtotal')
            ->selectRaw(
                'FLOOR((subtotal - total) / 100) as bucket, COUNT(*) as count',
            )
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->pluck('count', 'bucket');

        if ($buckets->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        $minBucket = $buckets->keys()->min();
        $maxBucket = $buckets->keys()->max();

        $labels = [];
        $data = [];

        for ($i = $minBucket; $i <= $maxBucket; $i++) {
            $labels[] = '£'.$i.'–£'.($i + 1);
            $data[] = $buckets->get($i, 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Carts',
                    'data' => $data,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.6)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Saving per cart',
                    ],
                ],
            ],
        ];
    }
}
