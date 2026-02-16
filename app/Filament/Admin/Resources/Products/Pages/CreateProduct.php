<?php

namespace App\Filament\Admin\Resources\Products\Pages;

use App\Filament\Admin\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $hasTags = array_key_exists("tags_array", $data);

        $tags =
            $hasTags && is_array($data["tags_array"])
                ? $data["tags_array"]
                : [];

        unset($data["tags_array"]);

        $record = new Product();
        $record->forceFill($data);
        $record->save();

        if ($hasTags) {
            $record->syncTags($tags);
        }

        return $record;
    }
}
