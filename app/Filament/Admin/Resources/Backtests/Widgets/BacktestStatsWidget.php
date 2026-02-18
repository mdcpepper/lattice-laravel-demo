<?php

namespace App\Filament\Admin\Resources\Backtests\Widgets;

use App\Models\Backtest;
use App\Services\NanosecondDurationFormatter;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

class BacktestStatsWidget extends StatsOverviewWidget
{
    public ?Backtest $record = null;

    protected ?string $pollingInterval = '1s';

    protected function getColumns(): int|array|null
    {
        return [
            '!@lg' => 2,
            '@xl' => 4,
        ];
    }

    protected function getPollingInterval(): ?string
    {
        if ($this->getProgress() == 100) {
            return null;
        }

        return $this->pollingInterval;
    }

    protected function getProgress(): int
    {
        if (
            ! $this->record instanceof Backtest ||
            $this->record->total_carts === 0
        ) {
            return 0;
        }

        return (int) round(
            ($this->record->processed_carts / $this->record->total_carts) * 100,
        );
    }

    protected function getStats(): array
    {
        if (! $this->record instanceof Backtest) {
            return [];
        }

        $items = $this->record->simulatedCartItems();

        $totalItems = $items->count();

        $discountedItems = $items
            ->clone()
            ->whereColumn(
                'backtested_cart_items.offer_price',
                '<',
                'backtested_cart_items.price',
            )
            ->count();

        $percentDiscounted =
            $totalItems > 0
                ? round(($discountedItems / $totalItems) * 100, 1)
                : 0.0;

        $totalSavingsPence =
            (int) ($items
                ->clone()
                ->selectRaw(
                    'COALESCE(SUM(backtested_cart_items.price - backtested_cart_items.offer_price), 0) as savings',
                )
                ->value('savings') ?? 0);

        $avgItemSavingPence =
            $discountedItems > 0
                ? (int) round($totalSavingsPence / $discountedItems)
                : 0;

        $avgCartSavingPence =
            $this->record->total_carts > 0
                ? (int) round($totalSavingsPence / $this->record->total_carts)
                : 0;

        $processingTimeStats = $this->timingStats('processing_time');
        $solveTimeStats = $this->timingStats('solve_time');

        return [
            Stat::make(
                'Progress',
                "{$this->record->processed_carts}/{$this->record->total_carts} ({$this->getProgress()}%)",
            ),

            Stat::make(
                'Items discounted',
                "{$discountedItems}/$totalItems ({$percentDiscounted}%)",
            ),

            Stat::make(
                'Avg. discount per item',
                $this->formatMoney($avgItemSavingPence),
            ),

            Stat::make(
                'Avg. saving per cart',
                $this->formatMoney($avgCartSavingPence),
            ),
            ...$this->buildTimingStats('End-to-end', $processingTimeStats),
            ...$this->buildTimingStats('Solve', $solveTimeStats),
        ];
    }

    private function formatMoney(int $pence): string
    {
        return '£'.number_format($pence / 100, 2);
    }

    /**
     * @return array{p50: ?float, p90: ?float}
     */
    private function timingStats(string $column): array
    {
        if (! $this->record instanceof Backtest) {
            return $this->emptyTimingStats();
        }

        /** @var Collection<int, float> $times */
        $times = $this->record
            ->backtestedCarts()
            ->whereNotNull($column)
            ->orderBy($column)
            ->pluck($column)
            ->map(fn (mixed $value): float => (float) $value)
            ->values();

        if ($times->isEmpty()) {
            return $this->emptyTimingStats();
        }

        return [
            'p50' => $this->percentile($times, 50),
            'p90' => $this->percentile($times, 90),
        ];
    }

    /**
     * @param  array{p50: ?float, p90: ?float}  $timingStats
     * @return array<int, Stat>
     */
    private function buildTimingStats(string $prefix, array $timingStats): array
    {
        return [
            Stat::make(
                "{$prefix}: P50",
                NanosecondDurationFormatter::format($timingStats['p50']),
            ),
            Stat::make(
                "{$prefix}: P90",
                NanosecondDurationFormatter::format($timingStats['p90']),
            ),
        ];
    }

    /**
     * @return array{p50: ?float, p90: ?float}
     */
    private function emptyTimingStats(): array
    {
        return [
            'p50' => null,
            'p90' => null,
        ];
    }

    /**
     * @param  Collection<int, float>  $sortedValues
     */
    private function percentile(Collection $sortedValues, float $percent): float
    {
        $count = $sortedValues->count();

        if ($count === 0) {
            return 0.0;
        }

        $clampedPercent = max(0.0, min(100.0, $percent));
        $rank = (int) ceil(($clampedPercent / 100) * $count);
        $index = max(0, $rank - 1);

        return (float) $sortedValues->get(
            $index,
            (float) $sortedValues->last(),
        );
    }
}
