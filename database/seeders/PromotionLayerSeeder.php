<?php

namespace Database\Seeders;

use App\Enums\PromotionLayerOutputMode;
use App\Enums\PromotionLayerOutputTargetMode;
use App\Models\PromotionLayer;
use App\Models\PromotionStack;
use Illuminate\Database\Seeder;

class PromotionLayerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PromotionStack::query()
            ->doesntHave('layers')
            ->get()
            ->each(function (PromotionStack $stack): void {
                $participatingLayer = PromotionLayer::factory()
                    ->for($stack, 'stack')
                    ->create([
                        'reference' => 'eligible',
                        'name' => 'Eligible',
                        'sort_order' => 1,
                    ]);

                $rootLayer = PromotionLayer::factory()
                    ->for($stack, 'stack')
                    ->create([
                        'reference' => 'root',
                        'name' => 'Root',
                        'sort_order' => 0,
                        'output_mode' => PromotionLayerOutputMode::Split->value,
                        'participating_output_mode' => PromotionLayerOutputTargetMode::Layer->value,
                        'participating_output_layer_id' => $participatingLayer->id,
                        'non_participating_output_mode' => PromotionLayerOutputTargetMode::PassThrough->value,
                    ]);

                $stack->update([
                    'root_layer_reference' => $rootLayer->reference,
                ]);
            });
    }
}
