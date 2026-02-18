<?php

namespace App\Filament\Admin\Resources\Carts\Tables;

use App\Models\Cart;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CartsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ulid')
                    ->label('ID')
                    ->searchable()
                    ->fontFamily('mono'),

                TextColumn::make('customer.name')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('email')->placeholder('-')->searchable(),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),

                TextColumn::make('subtotal')->money()->sortable(),

                TextColumn::make('discount')
                    ->label('Discount')
                    ->state(
                        fn (
                            Cart $record,
                        ): int => (int) $record->subtotal->getAmount() -
                            (int) $record->total->getAmount(),
                    )
                    ->money(
                        currency: fn (
                            Cart $record,
                        ): string => $record->total_currency,
                        divideBy: 100,
                    )
                    ->sortable(
                        query: fn (
                            Builder $query,
                            string $direction,
                        ): Builder => $query->orderByRaw(
                            "subtotal - total {$direction}",
                        ),
                    ),

                TextColumn::make('total')->money()->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }
}
