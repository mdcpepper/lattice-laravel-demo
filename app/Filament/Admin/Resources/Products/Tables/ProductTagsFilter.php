<?php

namespace App\Filament\Admin\Resources\Products\Tables;

use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Tags\Tag;

class ProductTagsFilter
{
    public static function make(): SelectFilter
    {
        return SelectFilter::make('tags_array')
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
                    ->filter(fn (mixed $value): bool => is_numeric($value))
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
            });
    }
}
