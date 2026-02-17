<?php

namespace Database\Seeders;

use App\Models\PromotionLayer;
use App\Models\PromotionStack;
use App\Models\Team;
use Illuminate\Database\Seeder;

class PromotionStackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $team = Team::query()->first();

        if (! $team instanceof Team) {
            return;
        }

        $stack = PromotionStack::factory()
            ->for($team)
            ->create([
                'name' => 'Default Promotion Stack',
            ]);

        $rootLayer = PromotionLayer::factory()
            ->for($stack, 'stack')
            ->create([
                'reference' => 'root',
                'name' => 'Root Layer',
            ]);

        $stack->update(['root_layer_reference' => $rootLayer->reference]);
    }
}
