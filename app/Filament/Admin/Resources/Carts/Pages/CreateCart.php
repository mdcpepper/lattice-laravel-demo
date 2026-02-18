<?php

namespace App\Filament\Admin\Resources\Carts\Pages;

use App\Filament\Admin\Resources\Carts\CartResource;
use App\Models\PromotionStack;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateCart extends CreateRecord
{
    protected static string $resource = CartResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['team_id'] = Filament::getTenant()->id;

        $activeStack = PromotionStack::activeForTeam((int) $data['team_id']);
        $data['promotion_stack_id'] = $activeStack?->id;

        return $data;
    }
}
