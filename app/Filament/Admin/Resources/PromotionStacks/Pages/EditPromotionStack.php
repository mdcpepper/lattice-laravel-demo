<?php

namespace App\Filament\Admin\Resources\PromotionStacks\Pages;

use App\Filament\Admin\Resources\PromotionStacks\Concerns\InteractsWithPromotionStackLayers;
use App\Filament\Admin\Resources\PromotionStacks\PromotionStackResource;
use App\Models\Promotions\PromotionStack;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @extends EditRecord<Model>
 */
class EditPromotionStack extends EditRecord
{
    use InteractsWithPromotionStackLayers;

    protected static string $resource = PromotionStackResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getFormActions(): array
    {
        $actions = parent::getFormActions();
        array_splice($actions, 1, 0, [$this->saveAsNewAction()]);

        return $actions;
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
     *
     * @throws \Throwable
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

    protected function saveAsNewAction(): Action
    {
        return Action::make('saveAsNew')
            ->label('Save as New')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->form([TextInput::make('name')->required()->maxLength(255)])
            ->action(function (array $data): void {
                /** @var PromotionStack $original */
                $original = $this->record;

                $newStack = DB::transaction(function () use (
                    $data,
                    $original,
                ): PromotionStack {
                    $stack = PromotionStack::query()->create([
                        'team_id' => $this->currentTeamId(),
                        'name' => $data['name'],
                        'root_layer_reference' => null,
                        'active_from' => null,
                        'active_to' => null,
                    ]);

                    $formData = $this->populateLayerFormData($original, []);
                    $formData['name'] = $data['name'];
                    $formData['active_from'] = null;
                    $formData['active_to'] = null;

                    $this->syncStackGraph($stack, $formData);

                    return $stack;
                });

                $this->redirect(
                    PromotionStackResource::getUrl('edit', [
                        'record' => $newStack,
                    ]),
                );
            });
    }
}
