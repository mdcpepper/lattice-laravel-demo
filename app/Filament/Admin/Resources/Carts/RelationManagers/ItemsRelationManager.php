<?php

namespace App\Filament\Admin\Resources\Carts\RelationManagers;

use App\Filament\Admin\Resources\Carts\Tables\ProductSelectionTable;
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
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = "items";

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute("ulid")
            ->columns([
                TextColumn::make("ulid")
                    ->label("ID")
                    ->fontFamily("mono")
                    ->searchable(),

                TextColumn::make("product.name")->searchable(),

                TextColumn::make("product.price")->money()->sortable(),

                TextColumn::make("created_at")
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([TrashedFilter::make()])
            ->headerActions([
                Action::make("addItem")
                    ->label("Add item(s)")
                    ->slideOver()
                    ->modalHeading("Add item(s)")
                    ->modalSubmitActionLabel("Add to cart")
                    ->schema([
                        TableSelect::make("product_id")
                            ->tableConfiguration(ProductSelectionTable::class)
                            ->hiddenLabel()
                            ->multiple()
                            ->required(),
                    ])
                    ->action(function (
                        array $data,
                        RelationManager $livewire,
                    ): void {
                        foreach ($data["product_id"] as $productId) {
                            $livewire->getRelationship()->create([
                                "product_id" => $productId,
                            ]);
                        }
                    }),
            ])
            ->recordActions([
                DeleteAction::make()->label("Remove"),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label("Remove selected"),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(
                fn(Builder $query) => $query->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]),
            );
    }
}
