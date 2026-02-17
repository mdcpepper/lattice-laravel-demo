<?php

namespace App\Filament\Admin\Resources\PromotionStacks\Pages;

use App\Filament\Admin\Resources\PromotionStacks\PromotionStackResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPromotionStacks extends ListRecords
{
    protected static string $resource = PromotionStackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
