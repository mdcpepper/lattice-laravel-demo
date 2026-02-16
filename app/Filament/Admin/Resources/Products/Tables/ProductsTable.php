<?php

namespace App\Filament\Admin\Resources\Products\Tables;

use App\Models\Product;
use App\Services\ProductQualificationChecker;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Tags\Tag;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        $checker = resolve(ProductQualificationChecker::class);

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
                    ->listWithLineBreaks(),
                TextColumn::make('qualifying_promotions')
                    ->label('Qualifying Promotions')
                    ->state(
                        fn (
                            Product $record,
                        ): array => $checker->qualifyingPromotionNames($record),
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
                SelectFilter::make('tags_array')
                    ->label('Tags')
                    ->searchable()
                    ->options(function (): array {
                        return Tag::query()
                            ->whereNull('type')
                            ->get()
                            ->mapWithKeys(function (Tag $tag): array {
                                $name = $tag->getTranslation('name', 'en');

                                if ($name === '') {
                                    return [];
                                }

                                return [(string) $tag->id => $name];
                            })
                            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                            ->all();
                    })
                    ->multiple()
                    ->attribute(fn (): string => 'id')
                    ->query(function (Builder $query, array $data): Builder {
                        $tagIds = collect($data['values'] ?? [])
                            ->filter(
                                fn (mixed $value): bool => is_numeric($value),
                            )
                            ->map(fn (mixed $value): int => (int) $value)
                            ->values()
                            ->all();

                        if ($tagIds === []) {
                            return $query;
                        }

                        return $query->whereHas(
                            'tags',
                            fn (Builder $tagQuery): Builder => $tagQuery
                                ->whereNull('tags.type')
                                ->whereIn('tags.id', $tagIds),
                        );
                    }),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
