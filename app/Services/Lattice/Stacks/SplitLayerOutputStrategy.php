<?php

declare(strict_types=1);

namespace App\Services\Lattice\Stacks;

use App\Enums\PromotionLayerOutputMode;
use App\Enums\PromotionLayerOutputTargetMode;
use App\Models\PromotionLayer as PromotionLayerModel;
use Lattice\Layer as LatticeLayer;
use Lattice\LayerOutput as LatticeLayerOutput;
use RuntimeException;

class SplitLayerOutputStrategy implements LatticeLayerOutputStrategy
{
    public function supports(PromotionLayerModel $layer): bool
    {
        return $this->outputMode($layer) ===
            PromotionLayerOutputMode::Split->value;
    }

    public function make(
        PromotionLayerModel $layer,
        array $latticeLayerIndex,
        ?LatticeLayer $passThroughLayer = null,
    ): LatticeLayerOutput {
        $participatingLayer = $this->resolveTargetLayer(
            layer: $layer,
            branch: 'participating',
            mode: $layer->participating_output_mode,
            layerId: $layer->participating_output_layer_id,
            latticeLayerIndex: $latticeLayerIndex,
            passThroughLayer: $passThroughLayer,
        );

        $nonParticipatingLayer = $this->resolveTargetLayer(
            layer: $layer,
            branch: 'non-participating',
            mode: $layer->non_participating_output_mode,
            layerId: $layer->non_participating_output_layer_id,
            latticeLayerIndex: $latticeLayerIndex,
            passThroughLayer: $passThroughLayer,
        );

        return LatticeLayerOutput::split(
            participating: $participatingLayer,
            nonParticipating: $nonParticipatingLayer,
        );
    }

    /**
     * @param  array<int, LatticeLayer>  $latticeLayerIndex
     */
    private function resolveTargetLayer(
        PromotionLayerModel $layer,
        string $branch,
        mixed $mode,
        mixed $layerId,
        array $latticeLayerIndex,
        ?LatticeLayer $passThroughLayer,
    ): LatticeLayer {
        $normalizedMode = $this->targetMode($mode);

        if ($normalizedMode === PromotionLayerOutputTargetMode::PassThrough) {
            if ($passThroughLayer instanceof LatticeLayer) {
                return $passThroughLayer;
            }

            throw new RuntimeException(
                sprintf(
                    'Layer [%s] %s output cannot pass through because no pass-through layer is configured.',
                    $layer->reference,
                    $branch,
                ),
            );
        }

        if ($normalizedMode === PromotionLayerOutputTargetMode::Layer) {
            if (! is_numeric($layerId)) {
                throw new RuntimeException(
                    sprintf(
                        'Layer [%s] %s output must reference a target layer.',
                        $layer->reference,
                        $branch,
                    ),
                );
            }

            $resolvedLayer = $latticeLayerIndex[(int) $layerId] ?? null;

            if (! $resolvedLayer instanceof LatticeLayer) {
                throw new RuntimeException(
                    sprintf(
                        'Layer [%s] %s output references unknown layer id [%s].',
                        $layer->reference,
                        $branch,
                        (string) $layerId,
                    ),
                );
            }

            return $resolvedLayer;
        }

        throw new RuntimeException(
            sprintf(
                'Layer [%s] %s output has unsupported mode [%s].',
                $layer->reference,
                $branch,
                is_string($mode) ? $mode : get_debug_type($mode),
            ),
        );
    }

    private function outputMode(PromotionLayerModel $layer): string
    {
        $outputMode = $layer->output_mode;

        if ($outputMode instanceof \BackedEnum) {
            return (string) $outputMode->value;
        }

        return is_string($outputMode) ? $outputMode : '';
    }

    private function targetMode(mixed $mode): ?PromotionLayerOutputTargetMode
    {
        if ($mode instanceof PromotionLayerOutputTargetMode) {
            return $mode;
        }

        if ($mode instanceof \BackedEnum && is_string($mode->value)) {
            return PromotionLayerOutputTargetMode::tryFrom($mode->value);
        }

        if (is_string($mode)) {
            return PromotionLayerOutputTargetMode::tryFrom($mode);
        }

        return null;
    }
}
