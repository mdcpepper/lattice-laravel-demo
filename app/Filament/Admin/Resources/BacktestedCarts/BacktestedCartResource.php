<?php

namespace App\Filament\Admin\Resources\BacktestedCarts;

use App\Filament\Admin\Resources\BacktestedCarts\Pages\ViewBacktestedCart;
use App\Filament\Admin\Resources\BacktestedCarts\RelationManagers\ItemsRelationManager;
use App\Filament\Admin\Resources\BacktestedCarts\Schemas\BacktestedCartInfolist;
use App\Filament\Admin\Resources\BacktestedCarts\Widgets\BacktestedCartStatsWidget;
use App\Filament\Admin\Resources\Backtests\BacktestResource;
use App\Models\Backtests\BacktestedCart;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class BacktestedCartResource extends Resource
{
    protected static ?string $model = BacktestedCart::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $recordTitleAttribute = 'ulid';

    protected static bool $shouldRegisterNavigation = false;

    public static function infolist(Schema $schema): Schema
    {
        return BacktestedCartInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getWidgets(): array
    {
        return [BacktestedCartStatsWidget::class];
    }

    public static function getIndexUrl(
        array $parameters = [],
        bool $isAbsolute = true,
        ?string $panel = null,
        ?Model $tenant = null,
        bool $shouldGuessMissingParameters = false,
    ): string {
        return BacktestResource::getUrl(
            'index',
            $parameters,
            $isAbsolute,
            $panel,
            $tenant,
            $shouldGuessMissingParameters,
        );
    }

    public static function getPages(): array
    {
        return [
            'view' => ViewBacktestedCart::route('/{record}'),
        ];
    }
}
