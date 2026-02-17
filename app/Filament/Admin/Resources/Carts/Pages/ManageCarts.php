<?php

namespace App\Filament\Admin\Resources\Carts\Pages;

use App\Filament\Admin\Resources\Carts\CartResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ManageCarts extends ListRecords
{
    protected static string $resource = CartResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
