<?php

namespace App\Filament\Admin\Resources\Products\Pages;

use App\Filament\Admin\Resources\Products\ProductResource;
use Cknow\Money\Money;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Tags\HasTags;

/**
 * @extends EditRecord<Model>
 */
class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data["tags_array"] = $this->getRecord()->tags_array;

        if ($this->getRecord()->price instanceof Money) {
            $data["price"] = $this->getRecord()->price->formatByDecimal();
        }

        return $data;
    }

    /**
     * @param Model&HasTags $record
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $hasTags = array_key_exists("tags_array", $data);
        $hasPrice = array_key_exists("price", $data);

        $tags =
            $hasTags && is_array($data["tags_array"])
                ? $data["tags_array"]
                : [];

        if ($hasPrice && is_scalar($data["price"])) {
            $data["price"] = (int) Money::parseByDecimal(
                (string) $data["price"],
                "GBP",
            )->getAmount();
        }

        unset($data["tags_array"]);

        $record->forceFill($data);
        $record->save();

        if ($hasTags) {
            $record->syncTags($tags);
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
