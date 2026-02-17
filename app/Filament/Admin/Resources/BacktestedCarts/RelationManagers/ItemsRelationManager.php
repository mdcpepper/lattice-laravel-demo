<?php

namespace App\Filament\Admin\Resources\BacktestedCarts\RelationManagers;

use App\Models\BacktestedCartItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ulid')
            ->columns([
                TextColumn::make('product.name')->searchable(),

                TextColumn::make('price')->money('GBP')->sortable(),

                TextColumn::make('offer_price')->money('GBP')->sortable(),

                TextColumn::make('discount')
                    ->label('Discount')
                    ->state(
                        fn (BacktestedCartItem $record): string => '£'.
                            number_format(
                                ($record->price->getAmount() -
                                    $record->offer_price->getAmount()) /
                                    100,
                                2,
                            ),
                    )
                    ->sortable(
                        query: fn (
                            $query,
                            string $direction,
                        ) => $query->orderByRaw(
                            "price - offer_price {$direction}",
                        ),
                    ),

                TextColumn::make('promotion_names')
                    ->label('Promotion(s)')
                    ->state(
                        fn (
                            BacktestedCartItem $record,
                        ): string => $record->redemptions
                            ->pluck('promotion.name')
                            ->filter()
                            ->unique()
                            ->join(', '),
                    )
                    ->placeholder('-'),
            ])
            ->filters([])
            ->recordActions([])
            ->toolbarActions([])
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with([
                    'product',
                    'redemptions.promotion',
                ]),
            );
    }
}
