<?php

namespace App\Filament\Admin\Resources\Carts\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

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

            Select::make('promotion_stack_id')
                ->label('Promotion Stack')
                ->relationship(
                    'promotionStack',
                    'name',
                    fn (Builder $query): Builder => $query->where(
                        'team_id',
                        Filament::getTenant()->getKey(),
                    ),
                )
                ->searchable()
                ->preload()
                ->nullable()
                ->hiddenOn('create'),
        ]);
    }
}
