<?php

namespace App\Filament\Admin\Resources\Products\Pages;

use App\Filament\Admin\Resources\Products\ProductResource;
use App\Models\Product;
use Cknow\Money\Money;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $hasTags = array_key_exists('tags_array', $data);
        $hasPrice = array_key_exists('price', $data);

        $tags =
            $hasTags && is_array($data['tags_array'])
                ? $data['tags_array']
                : [];

        if ($hasPrice && is_scalar($data['price'])) {
            $data['price'] = (int) Money::parseByDecimal(
                (string) $data['price'],
                'GBP',
            )->getAmount();
        }

        unset($data['tags_array']);

        $record = new Product;
        $record->forceFill($data);
        $record->save();

        if ($hasTags) {
            $record->syncTags($tags);
        }

        return $record;
    }
}
