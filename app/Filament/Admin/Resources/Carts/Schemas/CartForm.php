<?php

namespace App\Filament\Admin\Resources\Carts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CartForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('customer_id')
                ->relationship('customer', 'name')
                ->searchable()
                ->preload()
                ->nullable(),

            TextInput::make('email')->email()->nullable(),
        ]);
    }
}
