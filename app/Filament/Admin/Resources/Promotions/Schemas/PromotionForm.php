<?php

namespace App\Filament\Admin\Resources\Promotions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([TextInput::make("name")]);
    }
}
