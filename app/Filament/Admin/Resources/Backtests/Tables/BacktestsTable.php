<?php

namespace App\Filament\Admin\Resources\Backtests\Tables;

use App\Enums\BacktestStatus;
use App\Models\Backtest;
use App\Models\BacktestedCartItem;
use App\Services\NanosecondDurationFormatter;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class BacktestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ->poll('5s')
            ->modifyQueryUsing(function (Builder $query): Builder {
                $tenant = Filament::getTenant();

                if ($tenant === null) {
                    return $query->whereRaw('1 = 0');
                }

                return $query
                    ->whereHas(
                        'promotionStack',
                        fn (Builder $q): Builder => $q->where(
                            'team_id',
                            $tenant->getKey(),
                        ),
                    )
                    ->withCount([
                        'simulatedCartItems as total_items',
                        'simulatedCartItems as discounted_items' => fn (
                            Builder $simulatedCartItemsQuery,
                        ): Builder => $simulatedCartItemsQuery->whereColumn(
                            'backtested_cart_items.offer_price',
                            '<',
                            'backtested_cart_items.price',
                        ),
                    ])
                    ->selectSub(
                        self::totalSavingsPerBacktestSubquery(),
                        'total_savings_minor',
                    )
                    ->selectSub(
                        self::p50PerBacktestSubquery('processing_time'),
                        'processing_time_p50',
                    )
                    ->selectSub(
                        self::p50PerBacktestSubquery('solve_time'),
                        'solve_time_p50',
                    )
                    ->latest();
            })
            ->columns([
                TextColumn::make('ulid')
                    ->label('ID')
                    ->fontFamily('mono')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('promotionStack.name')
                    ->label('Stack')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')->badge()->color(
                    fn (BacktestStatus $state): string => match ($state) {
                        BacktestStatus::Pending => 'gray',
                        BacktestStatus::Running => 'warning',
                        BacktestStatus::Completed => 'success',
                        BacktestStatus::Failed => 'danger',
                    },
                ),

                TextColumn::make('processed_carts')
                    ->label('Progress')
                    ->state(
                        fn (
                            Backtest $record,
                        ): string => "{$record->processed_carts} / {$record->total_carts}",
                    ),

                TextColumn::make('items_discounted')
                    ->label('Items discounted')
                    ->state(
                        fn (
                            Backtest $record,
                        ): string => self::itemsDiscountedLabel($record),
                    )
                    ->toggleable(),

                TextColumn::make('avg_discount_per_item')
                    ->label('Avg. discount per item')
                    ->state(
                        fn (Backtest $record): string => self::formatMoney(
                            self::avgDiscountPerItemMinor($record),
                        ),
                    )
                    ->toggleable(),

                TextColumn::make('avg_saving_per_cart')
                    ->label('Avg. saving per cart')
                    ->state(
                        fn (Backtest $record): string => self::formatMoney(
                            self::avgSavingPerCartMinor($record),
                        ),
                    )
                    ->toggleable(),

                TextColumn::make('processing_time_p50')
                    ->label('End-to-end: P50')
                    ->state(
                        fn (
                            Backtest $record,
                        ): string => NanosecondDurationFormatter::format(
                            self::percentileForColumn(
                                $record,
                                'processing_time',
                                50,
                            ),
                        ),
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('solve_time_p50')
                    ->label('Solve: P50')
                    ->state(
                        fn (
                            Backtest $record,
                        ): string => NanosecondDurationFormatter::format(
                            self::percentileForColumn(
                                $record,
                                'solve_time',
                                50,
                            ),
                        ),
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([ViewAction::make(), DeleteAction::make()]);
    }

    /**
     * @return array{total_items: int, discounted_items: int, total_savings_minor: int}
     */
    private static function backtestedItemMetrics(Backtest $backtest): array
    {
        return [
            'total_items' => (int) ($backtest->getAttribute('total_items') ?? 0),
            'discounted_items' => (int) ($backtest->getAttribute('discounted_items') ?? 0),
            'total_savings_minor' => (int) ($backtest->getAttribute('total_savings_minor') ?? 0),
        ];
    }

    private static function itemsDiscountedLabel(Backtest $backtest): string
    {
        $metrics = self::backtestedItemMetrics($backtest);
        $totalItems = $metrics['total_items'];
        $discountedItems = $metrics['discounted_items'];

        $percentDiscounted =
            $totalItems > 0
                ? number_format(($discountedItems / $totalItems) * 100, 1)
                : '0.0';

        return "{$discountedItems}/{$totalItems} ({$percentDiscounted}%)";
    }

    private static function avgDiscountPerItemMinor(Backtest $backtest): int
    {
        $metrics = self::backtestedItemMetrics($backtest);

        if ($metrics['discounted_items'] === 0) {
            return 0;
        }

        return (int) round(
            $metrics['total_savings_minor'] / $metrics['discounted_items'],
        );
    }

    private static function avgSavingPerCartMinor(Backtest $backtest): int
    {
        if ($backtest->total_carts === 0) {
            return 0;
        }

        $metrics = self::backtestedItemMetrics($backtest);

        return (int) round(
            $metrics['total_savings_minor'] / $backtest->total_carts,
        );
    }

    private static function formatMoney(int $pence): string
    {
        return '£'.number_format($pence / 100, 2);
    }

    private static function percentileForColumn(
        Backtest $backtest,
        string $column,
        float $percent,
    ): ?float {
        if ($percent !== 50.0) {
            return null;
        }

        $alias = match ($column) {
            'processing_time' => 'processing_time_p50',
            'solve_time' => 'solve_time_p50',
            default => null,
        };

        if ($alias === null) {
            return null;
        }

        $value = $backtest->getAttribute($alias);

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private static function p50PerBacktestSubquery(string $column): QueryBuilder
    {
        if (! in_array($column, ['processing_time', 'solve_time'], true)) {
            throw new \InvalidArgumentException(
                "Unsupported percentile column [{$column}].",
            );
        }

        return DB::table('backtested_carts as percentile_candidates')
            ->selectRaw("MIN(percentile_candidates.{$column})")
            ->whereColumn('percentile_candidates.backtest_id', 'backtests.id')
            ->whereNotNull("percentile_candidates.{$column}")
            ->whereRaw(
                "2 * (
                    SELECT COUNT(*)
                    FROM backtested_carts AS ranked_backtested_carts
                    WHERE ranked_backtested_carts.backtest_id = backtests.id
                        AND ranked_backtested_carts.{$column} IS NOT NULL
                        AND ranked_backtested_carts.{$column} <= percentile_candidates.{$column}
                ) >= (
                    SELECT COUNT(*)
                    FROM backtested_carts AS total_backtested_carts
                    WHERE total_backtested_carts.backtest_id = backtests.id
                        AND total_backtested_carts.{$column} IS NOT NULL
                )",
            );
    }

    private static function totalSavingsPerBacktestSubquery(): Builder
    {
        return BacktestedCartItem::query()
            ->selectRaw(
                'COALESCE(SUM(backtested_cart_items.price - backtested_cart_items.offer_price), 0)',
            )
            ->whereColumn('backtested_cart_items.backtest_id', 'backtests.id');
    }
}
