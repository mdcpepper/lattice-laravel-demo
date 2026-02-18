<?php

namespace App\Filament\Admin\Resources\Backtests\RelationManagers;

use App\Filament\Admin\Resources\BacktestedCarts\BacktestedCartResource;
use App\Models\BacktestedCart;
use App\Models\BacktestedCartItem;
use App\Services\NanosecondDurationFormatter;
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
            ->poll($this->getPoll())
            ->recordTitleAttribute('ulid')
            ->columns([
                TextColumn::make('cart.ulid')
                    ->label('Cart ID')
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),

                TextColumn::make('solve_time')
                    ->label('Solve')
                    ->formatStateUsing(
                        fn (
                            mixed $state,
                        ): string => NanosecondDurationFormatter::format(
                            is_numeric($state) ? (float) $state : null,
                        ),
                    )
                    ->sortable(),
                TextColumn::make('processing_time')
                    ->label('End-to-end')
                    ->formatStateUsing(
                        fn (
                            mixed $state,
                        ): string => NanosecondDurationFormatter::format(
                            is_numeric($state) ? (float) $state : null,
                        ),
                    )
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
                    'cart',
                    'items.redemptions.promotion',
                ]),
            )
            ->defaultSort('discount', 'desc');
    }

    private function getPoll(): ?string
    {
        if (
            $this->ownerRecord->processed_carts !=
            $this->ownerRecord->total_carts
        ) {
            return '2s';
        }

        return null;
    }
}
