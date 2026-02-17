<?php

namespace App\Filament\Admin\Resources\SimulatedCarts;

use App\Filament\Admin\Resources\SimulatedCarts\Pages\ViewSimulatedCart;
use App\Filament\Admin\Resources\SimulatedCarts\RelationManagers\ItemsRelationManager;
use App\Filament\Admin\Resources\SimulatedCarts\Schemas\SimulatedCartInfolist;
use App\Filament\Admin\Resources\SimulationRuns\SimulationRunResource;
use App\Models\SimulatedCart;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class SimulatedCartResource extends Resource
{
    protected static ?string $model = SimulatedCart::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $recordTitleAttribute = 'ulid';

    protected static bool $shouldRegisterNavigation = false;

    public static function infolist(Schema $schema): Schema
    {
        return SimulatedCartInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        return SimulationRunResource::getUrl('index', $parameters, $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters);
    }

    public static function getPages(): array
    {
        return [
            'view' => ViewSimulatedCart::route('/{record}'),
        ];
    }
}
