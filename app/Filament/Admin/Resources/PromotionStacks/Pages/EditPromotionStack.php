<?php

namespace App\Filament\Admin\Resources\PromotionStacks\Pages;

use App\Filament\Admin\Resources\PromotionStacks\Concerns\InteractsWithPromotionStackLayers;
use App\Filament\Admin\Resources\PromotionStacks\PromotionStackResource;
use App\Models\PromotionStack;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditPromotionStack extends EditRecord
{
    use InteractsWithPromotionStackLayers;

    protected static string $resource = PromotionStackResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var PromotionStack $stack */
        $stack = $this->record;

        return $this->populateLayerFormData($stack, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var PromotionStack $record */
        return DB::transaction(function () use (
            $record,
            $data,
        ): PromotionStack {
            $this->syncStackGraph($record, $data);

            return $record->fresh(['layers']) ?? $record;
        });
    }
}
