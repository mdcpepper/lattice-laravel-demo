<?php

namespace App\Filament\Admin\Resources\Carts\Widgets;

use App\Models\Cart;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CartStatsWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    public ?Cart $record = null;

    protected function getStats(): array
    {
        if (! $this->record instanceof Cart) {
            return [];
        }

        $subtotal = (int) $this->record->subtotal->getAmount();
        $total = (int) $this->record->total->getAmount();
        $discount = $subtotal - $total;

        $allItemCount = $this->record->items()->count();

        $discountedItemCount = $this->record
            ->items()
            ->whereColumn('offer_price', '<', 'price')
            ->count();

        return [
            Stat::make('Subtotal', $this->formatMoney($subtotal)),
            Stat::make('Discount', $this->formatMoney($discount)),
            Stat::make('Total', $this->formatMoney($total)),
            Stat::make(
                'Discounted items',
                "{$discountedItemCount}/{$allItemCount}",
            ),
        ];
    }

    private function formatMoney(int $amountInMinorUnits): string
    {
        return '£'.number_format($amountInMinorUnits / 100, 2);
    }
}
