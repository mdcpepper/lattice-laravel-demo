<?php

namespace App\Filament\Admin\Resources\BacktestedCarts\RelationManagers;

use App\Models\BacktestedCartItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ulid')
            ->columns([
                TextColumn::make('product.name')->searchable(),

                TextColumn::make('subtotal')->money('GBP')->sortable(),

                TextColumn::make('total')->money('GBP')->sortable(),

                TextColumn::make('discount')
                    ->label('Discount')
                    ->state(
                        fn (BacktestedCartItem $record): string => '£'.
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

                TextColumn::make('redemptions_count')
                    ->label('Promotions')
                    ->counts('redemptions')
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
