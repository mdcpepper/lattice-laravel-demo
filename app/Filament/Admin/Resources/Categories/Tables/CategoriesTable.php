<?php

namespace App\Filament\Admin\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with('mainProduct'),
            )
            ->columns([
                ImageColumn::make('main_product_image')
                    ->label('Thumbnail')
                    ->state(
                        fn (Category $record): ?string => $record->mainProduct
                            ?->thumb_url ?? $record->mainProduct?->image_url,
                    ),

                TextColumn::make('name')->searchable(),

                TextColumn::make('mainProduct.name')
                    ->label('Featured Product')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('slug'),

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
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
