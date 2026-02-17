<?php

namespace App\Filament\Admin\Resources\SimulationRuns\RelationManagers;

use App\Filament\Admin\Resources\SimulatedCarts\SimulatedCartResource;
use App\Models\SimulatedCart;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SimulatedCartsRelationManager extends RelationManager
{
    protected static string $relationship = 'simulatedCarts';

    public function table(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->recordTitleAttribute('ulid')
            ->columns([
                TextColumn::make('ulid')
                    ->label('ID')
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),

                TextColumn::make('subtotal')->label('Subtotal'),

                TextColumn::make('total')->label('Total'),

                TextColumn::make('discount')
                    ->label('Discount')
                    ->state(
                        fn (SimulatedCart $record): string => '£'.
                            number_format(
                                ($record->subtotal->getAmount() -
                                    $record->total->getAmount()) /
                                    100,
                                2,
                            ),
                    )
                    ->sortable(
                        query: fn (
                            $query,
                            string $direction,
                        ) => $query->orderByRaw(
                            "subtotal - total {$direction}",
                        ),
                    ),
            ])
            ->filters([])
            ->recordActions([
                ViewAction::make()->url(
                    fn (
                        SimulatedCart $record,
                    ): string => SimulatedCartResource::getUrl('view', [
                        'record' => $record,
                    ]),
                ),
            ])
            ->toolbarActions([])
            ->defaultSort('discount', 'desc');
    }
}
