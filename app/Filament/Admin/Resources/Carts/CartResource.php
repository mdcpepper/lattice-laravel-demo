<?php

namespace App\Filament\Admin\Resources\Carts;

use App\Filament\Admin\Resources\Carts\Pages\ManageCarts;
use App\Filament\Admin\Resources\Carts\Tables\CartsTable;
use App\Models\Cart;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CartResource extends Resource
{
    protected static ?string $model = Cart::class;

    protected static string|UnitEnum|null $navigationGroup = 'Shop';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    public static function table(Table $table): Table
    {
        return CartsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCarts::route('/'),
        ];
    }
}
