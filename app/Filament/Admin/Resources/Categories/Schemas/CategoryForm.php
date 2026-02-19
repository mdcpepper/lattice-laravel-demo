<?php

namespace App\Filament\Admin\Resources\Categories\Schemas;

use App\Filament\Admin\Resources\Carts\Tables\ProductSelectionTable;
use App\Models\Category;
use App\Models\Product;
use Closure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ModalTableSelect;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),

            TextInput::make('slug')->required(),

            Hidden::make('current_category_id')
                ->afterStateHydrated(function (
                    Hidden $component,
                    ?Category $record,
                ): void {
                    $component->state($record?->getKey());
                })
                ->dehydrated(false),

            ModalTableSelect::make('main_product_id')
                ->label('Featured Product')
                ->placeholder('Select a featured product')
                ->relationship('mainProduct', 'name')
                ->getOptionLabelFromRecordUsing(function (
                    Product $record,
                ): HtmlString {
                    $name = e($record->name);
                    $thumbnailUrl = e($record->thumb_url ?: $record->image_url);

                    if (blank($thumbnailUrl)) {
                        return new HtmlString($name);
                    }

                    return new HtmlString(
                        "<span><img src=\"{$thumbnailUrl}\" alt=\"{$name} thumbnail\" style=\"width: 1.5rem; height: 1.5rem; object-fit: cover; border-radius: 0.25rem; display: inline-block; vertical-align: middle; margin-right: 0.5rem;\">{$name}</span>",
                    );
                })
                ->tableConfiguration(ProductSelectionTable::class)
                ->tableArguments(
                    fn (Get $get, ?Category $record, mixed $livewire): array => [
                        'category_id' => $get('current_category_id', isAbsolute: true) ??
                            static::resolveCategoryId($record, $livewire),
                    ],
                )
                ->rule(
                    fn (
                        ?Category $record,
                        mixed $livewire,
                    ): Closure => function (
                        string $attribute,
                        mixed $value,
                        Closure $fail,
                    ) use ($record, $livewire): void {
                        $categoryId = static::resolveCategoryId(
                            $record,
                            $livewire,
                        );

                        if (! is_numeric($value) || ! is_numeric($categoryId)) {
                            return;
                        }

                        $belongsToCategory = Product::query()
                            ->whereKey((int) $value)
                            ->where('category_id', (int) $categoryId)
                            ->exists();

                        if (! $belongsToCategory) {
                            $fail(
                                'The selected main product must belong to this category.',
                            );
                        }
                    },
                ),
        ]);
    }

    private static function resolveCategoryId(
        ?Category $record,
        mixed $livewire,
    ): ?int {
        if ($record instanceof Category) {
            return (int) $record->getKey();
        }

        if (is_object($livewire) && method_exists($livewire, 'getRecord')) {
            $livewireRecord = $livewire->getRecord();

            if ($livewireRecord instanceof Category) {
                return (int) $livewireRecord->getKey();
            }
        }

        return null;
    }
}
