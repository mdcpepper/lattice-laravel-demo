<?php

namespace App\Filament\Admin\Resources\PromotionStacks\Tables;

use App\Enums\BacktestStatus;
use App\Filament\Admin\Resources\Backtests\BacktestResource;
use App\Jobs\ProcessCartBacktestJob;
use App\Models\Backtest;
use App\Models\Cart;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Bus\PendingChain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;

class PromotionStacksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->withCount('layers'),
            )
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),

                TextColumn::make('root_layer_reference')
                    ->label('Root Layer')
                    ->placeholder('Unset'),

                TextColumn::make('layers_count')
                    ->label('Layers')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('active_from')->date()->sortable(),

                TextColumn::make('active_to')
                    ->date()
                    ->placeholder('Open-ended')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('runBacktest')
                    ->label('Run Backtest')
                    ->requiresConfirmation()
                    ->modalDescription(function () {
                        $count = Cart::query()
                            ->where('team_id', Filament::getTenant()->id)
                            ->count();

                        return "This will backtest {$count} cart(s) through this promotion stack. No actual records will be modified.";
                    })
                    ->action(function ($record, Action $action): void {
                        $teamId = Filament::getTenant()->id;

                        $cartIds = Cart::query()
                            ->where('team_id', $teamId)
                            ->pluck('id');

                        $backtestRun = Backtest::query()->create([
                            'promotion_stack_id' => $record->id,
                            'total_carts' => $cartIds->count(),
                            'processed_carts' => 0,
                            'status' => BacktestStatus::Running,
                        ]);

                        /** @var PendingChain $chain */
                        $chain = Bus::chain(
                            $cartIds
                                ->map(
                                    fn (
                                        int $cartId,
                                    ): ProcessCartBacktestJob => new ProcessCartBacktestJob(
                                        backtestRunId: $backtestRun->id,
                                        cartId: $cartId,
                                    ),
                                )
                                ->all(),
                        );

                        $chain->dispatch();

                        $action->successRedirectUrl(
                            BacktestResource::getUrl('view', [
                                'record' => $backtestRun,
                            ]),
                        );
                    })
                    ->successNotificationTitle('Backtest started'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
