<?php

declare(strict_types=1);

namespace App\Services\Lattice\Stacks;

use App\Enums\PromotionLayerOutputMode;
use App\Models\Promotions\PromotionLayer as PromotionLayerModel;
use Lattice\Stack\Layer as LatticeLayer;
use Lattice\Stack\LayerOutput as LatticeLayerOutput;

class PassThroughLayerOutputStrategy implements LatticeLayerOutputStrategy
{
    public function supports(PromotionLayerModel $layer): bool
    {
        return $this->outputMode($layer) ===
            PromotionLayerOutputMode::PassThrough->value;
    }

    public function make(
        PromotionLayerModel $layer,
        array $latticeLayerIndex,
        ?LatticeLayer $passThroughLayer = null,
    ): LatticeLayerOutput {
        return LatticeLayerOutput::passThrough();
    }

    private function outputMode(PromotionLayerModel $layer): string
    {
        $outputMode = $layer->output_mode;

        if ($outputMode instanceof \BackedEnum) {
            return (string) $outputMode->value;
        }

        return is_string($outputMode) ? $outputMode : '';
    }
}
