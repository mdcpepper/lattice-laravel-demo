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
use RuntimeException;

class RunBacktestAction
{
    public static function make(): Action
    {
        return Action::make('runBacktest')
            ->label('Run Backtest')
            ->requiresConfirmation()
            ->modalDescription(function (): string {
                $tenant = Filament::getTenant();

                if ($tenant === null) {
                    throw new RuntimeException(
                        'A tenant must be selected to run backtests.',
                    );
                }

                $count = Cart::query()
                    ->where('team_id', (int) $tenant->getKey())
                    ->count();

                return "This will backtest {$count} cart(s) through this promotion stack. No actual records will be modified.";
            })
            ->action(function ($record, Action $action): void {
                $tenant = Filament::getTenant();

                if ($tenant === null) {
                    throw new RuntimeException(
                        'A tenant must be selected to run backtests.',
                    );
                }

                $teamId = (int) $tenant->getKey();

                $cartIds = Cart::query()
                    ->where('team_id', $teamId)
                    ->pluck('id');

                $backtestRun = Backtest::query()->create([
                    'promotion_stack_id' => $record->id,
                    'total_carts' => $cartIds->count(),
                    'processed_carts' => 0,
                    'status' => BacktestStatus::Running,
                ]);

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
