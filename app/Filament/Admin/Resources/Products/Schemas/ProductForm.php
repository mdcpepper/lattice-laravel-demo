<?php

namespace App\Filament\Admin\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

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
            TextInput::make("stock")->required()->numeric(),
            TextInput::make("price")->required()->numeric()->prefix("£"),
            FileUpload::make("image_url")->image()->required(),
            FileUpload::make("thumb_url")->image()->required(),
        ]);
    }
}
