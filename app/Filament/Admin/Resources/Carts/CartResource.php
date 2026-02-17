<?php

namespace App\Filament\Admin\Resources\Carts;

use App\Filament\Admin\Resources\Carts\Pages\CreateCart;
use App\Filament\Admin\Resources\Carts\Pages\EditCart;
use App\Filament\Admin\Resources\Carts\Pages\ManageCarts;
use App\Filament\Admin\Resources\Carts\Pages\ViewCart;
use App\Filament\Admin\Resources\Carts\RelationManagers\ItemsRelationManager;
use App\Filament\Admin\Resources\Carts\Schemas\CartForm;
use App\Filament\Admin\Resources\Carts\Tables\CartsTable;
use App\Models\Cart;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CartResource extends Resource
{
    protected static ?string $model = Cart::class;

    protected static string|UnitEnum|null $navigationGroup = 'Shop';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    public static function form(Schema $schema): Schema
    {
        return CartForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CartsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCarts::route('/'),
            'create' => CreateCart::route('/create'),
            'view' => ViewCart::route('/{record}'),
            'edit' => EditCart::route('/{record}/edit'),
        ];
    }
}
