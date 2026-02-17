<?php

namespace App\Filament\Admin\Resources\Carts\Pages;

use App\Events\CartRecalculationRequested;
use App\Filament\Admin\Resources\Carts\CartResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends EditRecord<Model>
 */
class EditCart extends EditRecord
{
    protected static string $resource = CartResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        if (! $this->record->wasChanged('promotion_stack_id')) {
            return;
        }

        CartRecalculationRequested::dispatch($this->record->id);
    }
}
