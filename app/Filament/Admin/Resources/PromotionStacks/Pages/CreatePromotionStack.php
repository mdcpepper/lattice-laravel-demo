<?php

namespace App\Filament\Admin\Resources\PromotionStacks\Pages;

use App\Filament\Admin\Resources\PromotionStacks\Concerns\InteractsWithPromotionStackLayers;
use App\Filament\Admin\Resources\PromotionStacks\PromotionStackResource;
use App\Models\PromotionStack;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreatePromotionStack extends CreateRecord
{
    use InteractsWithPromotionStackLayers;

    protected static string $resource = PromotionStackResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): PromotionStack {
            $stack = PromotionStack::query()->create([
                'team_id' => $this->currentTeamId(),
                'name' => (string) $data['name'],
                'root_layer_reference' => null,
            ]);

            $this->syncStackGraph($stack, $data);

            return $stack;
        });
    }
}
