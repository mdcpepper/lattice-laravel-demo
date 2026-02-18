<?php

namespace App\Filament\Admin\Resources\Backtests\RelationManagers;

use App\Filament\Admin\Resources\BacktestedCarts\BacktestedCartResource;
use App\Models\BacktestedCart;
use App\Models\BacktestedCartItem;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BacktestedCartsRelationManager extends RelationManager
{
    protected static string $relationship = 'backtestedCarts';

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

                TextColumn::make('discount')
                    ->label('Discount')
                    ->state(
                        fn (BacktestedCart $record): string => '£'.
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

                TextColumn::make('total')->label('Total'),

                TextColumn::make('promotion_names')
                    ->label('Promotion(s)')
                    ->state(
                        fn (BacktestedCart $record): string => $record->items
                            ->flatMap(
                                fn (
                                    BacktestedCartItem $item,
                                ) => $item->redemptions->pluck(
                                    'promotion.name',
                                ),
                            )
                            ->filter()
                            ->unique()
                            ->join(', '),
                    )
                    ->placeholder('-'),
            ])
            ->filters([])
            ->recordActions([
                ViewAction::make()->url(
                    fn (
                        BacktestedCart $record,
                    ): string => BacktestedCartResource::getUrl('view', [
                        'record' => $record,
                    ]),
                ),
            ])
            ->toolbarActions([])
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with([
                    'items.redemptions.promotion',
                ]),
            )
            ->defaultSort('discount', 'desc');
    }
}
