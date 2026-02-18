<?php

namespace App\Filament\Admin\Resources\Products\Tables;

use App\Models\Product;
use App\Services\ProductQualificationChecker;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        $checker = resolve(ProductQualificationChecker::class);
        $tenantKey = Filament::getTenant()?->getKey();
        $teamId = is_numeric($tenantKey) ? (int) $tenantKey : null;

        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with('tags'),
            )
            ->columns([
                ImageColumn::make('thumb_url')->label('Image'),

                TextColumn::make('name')->searchable(),

                TextColumn::make('category.name')->searchable(),

                TextColumn::make('stock')->numeric()->sortable(),

                TextColumn::make('price')->money()->sortable(),

                TextColumn::make('tags_array')
                    ->label('Tags')
                    ->listWithLineBreaks()
                    ->badge(),

                TextColumn::make('qualifying_promotions')
                    ->label('Qualifying Promotions')
                    ->state(
                        fn (
                            Product $record,
                        ): array => $checker->qualifyingPromotionNames(
                            $record,
                            $teamId,
                        ),
                    )
                    ->listWithLineBreaks()
                    ->badge()
                    ->placeholder('None'),

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
                ProductTagsFilter::make(),
                QualifyingPromotionsFilter::make($checker, $teamId),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
