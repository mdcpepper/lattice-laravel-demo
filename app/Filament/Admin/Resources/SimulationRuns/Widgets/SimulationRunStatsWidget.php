<?php

namespace App\Filament\Admin\Resources\SimulationRuns\Widgets;

use App\Models\SimulationRun;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SimulationRunStatsWidget extends StatsOverviewWidget
{
    public ?SimulationRun $record = null;

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

    protected function getStats(): array
    {
        if (! $this->record instanceof SimulationRun) {
            return [];
        }

        $totalCarts = $this->record->total_carts;

        $items = $this->record->simulatedCartItems();

        $totalItems = $items->count();

        $discountedItems = $items
            ->clone()
            ->whereColumn(
                'simulated_cart_items.total',
                '<',
                'simulated_cart_items.subtotal',
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
                    'COALESCE(SUM(simulated_cart_items.subtotal - simulated_cart_items.total), 0) as savings',
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

        return [
            Stat::make(
                'Progress',
                "{$this->record->processed_carts}/{$this->record->total_carts} {$this->getProgress()}%",
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
        ];
    }

    private function formatMoney(int $pence): string
    {
        return '£'.number_format($pence / 100, 2);
    }
}
