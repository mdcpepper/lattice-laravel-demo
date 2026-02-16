<?php

namespace App\Filament\Admin\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Spatie\Tags\Tag;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make("name")->required(),
            Textarea::make("description")->required()->rows(5),
            Select::make("category_id")
                ->relationship("category", "name")
                ->required(),
            TagsInput::make("tags_array")
                ->label("Tags")
                ->trim()
                ->suggestions(function (): array {
                    return Tag::query()
                        ->get()
                        ->map(
                            fn(Tag $tag): string => $tag->getTranslation(
                                "name",
                                "en",
                            ),
                        )
                        ->filter(fn(string $name): bool => $name !== "")
                        ->unique()
                        ->sort()
                        ->values()
                        ->all();
                }),
            TextInput::make("stock")->required()->numeric(),
            TextInput::make("price")
                ->required()
                ->numeric()
                ->minValue(0)
                ->step("0.01")
                ->prefix("£"),
        ]);
    }
}
