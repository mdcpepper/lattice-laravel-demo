<?php

namespace App\Filament\Admin\Resources\PromotionStacks\Tables;

use App\Enums\SimulationRunStatus;
use App\Filament\Admin\Resources\SimulationRuns\SimulationRunResource;
use App\Jobs\ProcessSimulationCartJob;
use App\Models\Cart;
use App\Models\SimulationRun;
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
                Action::make('runSimulation')
                    ->label('Run Simulation')
                    ->requiresConfirmation()
                    ->modalDescription(function () {
                        $count = Cart::query()
                            ->where('team_id', Filament::getTenant()->id)
                            ->count();

                        return "This will simulate {$count} cart(s) through this promotion stack. No actual records will be modified.";
                    })
                    ->action(function ($record, Action $action): void {
                        $teamId = Filament::getTenant()->id;

                        $cartIds = Cart::query()
                            ->where('team_id', $teamId)
                            ->pluck('id');

                        $simulationRun = SimulationRun::query()->create([
                            'promotion_stack_id' => $record->id,
                            'total_carts' => $cartIds->count(),
                            'processed_carts' => 0,
                            'status' => SimulationRunStatus::Running,
                        ]);

                        /** @var PendingChain $chain */
                        $chain = Bus::chain(
                            $cartIds
                                ->map(
                                    fn (
                                        int $cartId,
                                    ): ProcessSimulationCartJob => new ProcessSimulationCartJob(
                                        simulationRunId: $simulationRun->id,
                                        cartId: $cartId,
                                    ),
                                )
                                ->all(),
                        );

                        $chain->dispatch();

                        $action->successRedirectUrl(
                            SimulationRunResource::getUrl('view', [
                                'record' => $simulationRun,
                            ]),
                        );
                    })
                    ->successNotificationTitle('Simulation started'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
