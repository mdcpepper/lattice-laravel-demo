<?php

namespace App\Filament\Admin\Resources\Carts\Pages;

use App\Filament\Admin\Resources\Carts\CartResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends ViewRecord<Model>
 */
class ViewCart extends ViewRecord
{
    protected static string $resource = CartResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
