<?php

namespace App\Filament\Admin\Resources\Backtests\Actions;

use App\Enums\BacktestStatus;
use App\Filament\Admin\Resources\Backtests\BacktestResource;
use App\Jobs\ProcessCartBacktestJob;
use App\Models\Backtest;
use App\Models\Cart;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Bus;

class RunBacktestAction
{
    public static function make(): Action
    {
        $tenant = Filament::getTenant();

        return Action::make('runBacktest')
            ->label('Run Backtest')
            ->requiresConfirmation()
            ->modalDescription(function () use ($tenant) {
                $count = Cart::query()->where('team_id', $tenant->id)->count();

                return "This will backtest {$count} cart(s) through this promotion stack. No actual records will be modified.";
            })
            ->action(function ($record, Action $action) use ($tenant): void {
                $teamId = $tenant->id;

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
            ->successNotificationTitle('Backtest started');
    }
}
