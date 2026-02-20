<?php

namespace App\Filament\Admin\Resources\PromotionStacks;

use App\Filament\Admin\Resources\PromotionStacks\Pages\CreatePromotionStack;
use App\Filament\Admin\Resources\PromotionStacks\Pages\EditPromotionStack;
use App\Filament\Admin\Resources\PromotionStacks\Pages\ListPromotionStacks;
use App\Filament\Admin\Resources\PromotionStacks\Schemas\PromotionStackForm;
use App\Filament\Admin\Resources\PromotionStacks\Tables\PromotionStacksTable;
use App\Models\Promotions\PromotionStack;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PromotionStackResource extends Resource
{
    protected static ?string $navigationLabel = 'Stacks';

    protected static ?string $model = PromotionStack::class;

    protected static string|UnitEnum|null $navigationGroup = 'Promotions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PromotionStackForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromotionStacksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromotionStacks::route('/'),
            'create' => CreatePromotionStack::route('/create'),
            'edit' => EditPromotionStack::route('/{record}/edit'),
        ];
    }
}
