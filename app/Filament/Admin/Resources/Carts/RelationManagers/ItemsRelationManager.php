<?php

namespace App\Filament\Admin\Resources\Carts\RelationManagers;

use App\Events\CartRecalculationRequested;
use App\Filament\Admin\Resources\Carts\Tables\ProductSelectionTable;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartManager;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TableSelect;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ulid')
            ->columns([
                TextColumn::make('ulid')
                    ->label('ID')
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable(),

                TextColumn::make('price')->label('Price')->money()->sortable(),

                TextColumn::make('promotion_names')
                    ->label('Promotion')
                    ->state(
                        fn (CartItem $record): string => $record->redemptions
                            ->pluck('promotion.name')
                            ->filter()
                            ->unique()
                            ->join(', '),
                    )
                    ->placeholder('-'),

                TextColumn::make('offer_price')
                    ->label('Offer Price')
                    ->money()
                    ->sortable(),

                TextColumn::make('discount')
                    ->label('Discount')
                    ->state(
                        fn (
                            CartItem $record,
                        ): int => (int) $record->price->getAmount() -
                            (int) $record->offer_price->getAmount(),
                    )
                    ->money(
                        currency: fn (
                            CartItem $record,
                        ): string => $record->offer_price_currency,
                        divideBy: 100,
                    )
                    ->sortable(
                        query: fn (
                            Builder $query,
                            string $direction,
                        ): Builder => $query->orderByRaw(
                            "price - offer_price {$direction}",
                        ),
                    ),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([TrashedFilter::make()])
            ->headerActions([
                Action::make('addItem')
                    ->label('Add item(s)')
                    ->slideOver()
                    ->modalHeading('Add item(s)')
                    ->modalSubmitActionLabel('Add to cart')
                    ->schema([
                        TableSelect::make('product_id')
                            ->tableConfiguration(ProductSelectionTable::class)
                            ->hiddenLabel()
                            ->multiple()
                            ->required(),
                    ])
                    ->action(function (
                        array $data,
                        RelationManager $livewire,
                        CartManager $cartManager,
                    ): void {
                        /** @var Cart $cart */
                        $cart = $livewire->getOwnerRecord();
                        $productsById = Product::query()
                            ->whereIn('id', $data['product_id'])
                            ->get()
                            ->keyBy('id');

                        foreach ($data['product_id'] as $productId) {
                            $product = $productsById->get($productId);

                            if (! $product instanceof Product) {
                                continue;
                            }

                            $cartManager->addItem(
                                $cart,
                                $product,
                                requestRecalculation: false,
                            );
                        }

                        $cartManager->requestRecalculation($cart);
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Remove')
                    ->action(function (
                        CartItem $record,
                        CartManager $cartManager,
                    ): void {
                        $cartManager->removeItem($record);
                    }),

                ForceDeleteAction::make()->action(function (
                    CartItem $record,
                ): void {
                    $cartId = (int) $record->cart_id;

                    $record->forceDelete();

                    CartRecalculationRequested::dispatch($cartId);
                }),

                RestoreAction::make()->action(function (
                    CartItem $record,
                ): void {
                    $record->restore();

                    CartRecalculationRequested::dispatch(
                        (int) $record->cart_id,
                    );
                }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Remove selected')
                        ->action(function (
                            Collection $records,
                            CartManager $cartManager,
                        ): void {
                            $cartIds = $records
                                ->pluck('cart_id')
                                ->map(fn (mixed $id): int => (int) $id)
                                ->unique()
                                ->values();

                            $records->each(function (CartItem $record) use (
                                $cartManager,
                            ): void {
                                $cartManager->removeItem(
                                    $record,
                                    requestRecalculation: false,
                                );
                            });

                            $cartIds->each(function (int $cartId): void {
                                CartRecalculationRequested::dispatch($cartId);
                            });
                        }),
                    ForceDeleteBulkAction::make()->action(function (
                        Collection $records,
                    ): void {
                        $cartIds = $records
                            ->pluck('cart_id')
                            ->map(fn (mixed $id): int => (int) $id)
                            ->unique()
                            ->values();

                        $records->each(function (CartItem $record): void {
                            $record->forceDelete();
                        });

                        $cartIds->each(function (int $cartId): void {
                            CartRecalculationRequested::dispatch($cartId);
                        });
                    }),
                    RestoreBulkAction::make()->action(function (
                        Collection $records,
                    ): void {
                        $cartIds = $records
                            ->pluck('cart_id')
                            ->map(fn (mixed $id): int => (int) $id)
                            ->unique()
                            ->values();

                        $records->each(function (CartItem $record): void {
                            $record->restore();
                        });

                        $cartIds->each(function (int $cartId): void {
                            CartRecalculationRequested::dispatch($cartId);
                        });
                    }),
                ]),
            ])
            ->modifyQueryUsing(
                fn (Builder $query) => $query
                    ->with(['product', 'redemptions.promotion'])
                    ->withoutGlobalScopes([SoftDeletingScope::class]),
            );
    }
}
