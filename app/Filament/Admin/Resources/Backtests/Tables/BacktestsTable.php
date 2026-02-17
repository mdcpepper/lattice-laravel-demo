<?php

namespace App\Filament\Admin\Resources\Backtests\Tables;

use App\Enums\BacktestStatus;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BacktestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query
                    ->whereHas(
                        'promotionStack',
                        fn (Builder $q): Builder => $q->where(
                            'team_id',
                            Filament::getTenant()->id,
                        ),
                    )
                    ->latest(),
            )
            ->columns([
                TextColumn::make('ulid')
                    ->label('ID')
                    ->fontFamily('mono')
                    ->searchable(),

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
                            $record,
                        ): string => "{$record->processed_carts} / {$record->total_carts}",
                    ),

                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([])
            ->recordActions([ViewAction::make(), DeleteAction::make()]);
    }
}
