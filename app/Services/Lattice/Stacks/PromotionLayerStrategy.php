<?php

declare(strict_types=1);

namespace App\Services\Lattice\Stacks;

use App\Models\Promotion;
use App\Models\PromotionLayer as PromotionLayerModel;
use App\Services\Lattice\Promotions\LatticePromotionFactory;
use Lattice\Layer as LatticeLayer;
use Lattice\LayerOutput as LatticeLayerOutput;
use Lattice\Promotions\Promotion as LatticePromotion;

class PromotionLayerStrategy implements LatticeLayerStrategy
{
    public function __construct(
        private readonly LatticePromotionFactory $latticePromotionFactory,
    ) {}

    public function supports(PromotionLayerModel $layer): bool
    {
        return true;
    }

    public function make(PromotionLayerModel $layer): LatticeLayer
    {
        if (! $layer->relationLoaded('promotions')) {
            $layer->load([
                'promotions' => fn ($query) => $query->withGraph(),
            ]);
        }

        /** @var array<int, LatticePromotion> $promotions */
        $promotions = $layer->promotions
            ->map(
                fn (
                    Promotion $promotion,
                ): LatticePromotion => $this->latticePromotionFactory->make(
                    $promotion,
                ),
            )
            ->all();

        return new LatticeLayer(
            reference: $layer,
            output: LatticeLayerOutput::passThrough(),
            promotions: $promotions,
        );
    }
}
