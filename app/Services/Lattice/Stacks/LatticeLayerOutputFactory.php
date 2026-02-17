<?php

declare(strict_types=1);

namespace App\Services\Lattice\Stacks;

use App\Models\PromotionLayer as PromotionLayerModel;
use Lattice\Layer as LatticeLayer;
use Lattice\LayerOutput as LatticeLayerOutput;
use RuntimeException;

class LatticeLayerOutputFactory
{
    /**
     * @param  array<int, LatticeLayerOutputStrategy>  $latticeLayerOutputStrategies
     */
    public function __construct(
        private readonly array $latticeLayerOutputStrategies,
    ) {}

    /**
     * @param  array<int, LatticeLayer>  $latticeLayerIndex
     */
    public function make(
        PromotionLayerModel $layer,
        array $latticeLayerIndex,
        ?LatticeLayer $passThroughLayer = null,
    ): LatticeLayerOutput {
        $strategy = collect($this->latticeLayerOutputStrategies)->first(
            fn (
                LatticeLayerOutputStrategy $strategy,
            ): bool => $strategy->supports($layer),
        );

        if (! $strategy instanceof LatticeLayerOutputStrategy) {
            throw $this->unsupportedOutputMode($layer);
        }

        return $strategy->make($layer, $latticeLayerIndex, $passThroughLayer);
    }

    private function unsupportedOutputMode(
        PromotionLayerModel $layer,
    ): RuntimeException {
        return new RuntimeException(
            sprintf(
                'Unsupported layer output mode [%s].',
                $this->outputMode($layer),
            ),
        );
    }

    private function outputMode(PromotionLayerModel $layer): string
    {
        $outputMode = $layer->output_mode;

        if ($outputMode instanceof \BackedEnum) {
            return (string) $outputMode->value;
        }

        if (is_string($outputMode) && $outputMode !== '') {
            return $outputMode;
        }

        return get_debug_type($outputMode);
    }
}
