<?php

namespace App\Filament\Admin\Resources\PromotionStacks\Tables;

use App\Filament\Admin\Resources\Backtests\Actions\RunBacktestAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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

                TextColumn::make('active_from')->dateTime()->sortable(),

                TextColumn::make('active_to')->dateTime()->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([EditAction::make(), RunBacktestAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
