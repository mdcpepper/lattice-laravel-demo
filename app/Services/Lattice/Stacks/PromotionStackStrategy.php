<?php

declare(strict_types=1);

namespace App\Services\Lattice\Stacks;

use App\Enums\PromotionLayerOutputMode;
use App\Enums\PromotionLayerOutputTargetMode;
use App\Models\PromotionLayer as PromotionLayerModel;
use App\Models\PromotionStack as PromotionStackModel;
use Illuminate\Database\Eloquent\Collection;
use Lattice\Layer as LatticeLayer;
use Lattice\LayerOutput as LatticeLayerOutput;
use Lattice\Stack as LatticeStack;
use Lattice\StackBuilder;
use RuntimeException;

class PromotionStackStrategy implements LatticeStackStrategy
{
    public function __construct(
        private readonly LatticeLayerFactory $latticeLayerFactory,
        private readonly LatticeLayerOutputFactory $latticeLayerOutputFactory,
    ) {}

    public function supports(PromotionStackModel $stack): bool
    {
        return true;
    }

    public function make(PromotionStackModel $stack): LatticeStack
    {
        if (! $stack->relationLoaded('layers')) {
            $stack->load([
                'layers' => fn ($query) => $query->orderBy('sort_order'),
            ]);
        }

        /** @var Collection<int, PromotionLayerModel> $layers */
        $layers = $stack->layers->sortBy('sort_order')->values();

        if ($layers->isEmpty()) {
            throw new RuntimeException('Promotion stack has no layers.');
        }

        if (
            $layers->contains(
                fn (PromotionLayerModel $layer): bool => ! $layer->relationLoaded(
                    'promotions',
                ),
            )
        ) {
            $layers->load([
                'promotions' => fn ($query) => $query->withGraph(),
            ]);
        }

        $stackBuilder = new StackBuilder;
        $passThroughLayer = null;

        if (
            $layers->contains(
                fn (
                    PromotionLayerModel $layer,
                ): bool => $this->requiresPassThroughSink($layer),
            )
        ) {
            $passThroughLayer = $stackBuilder->addLayer(
                new LatticeLayer(
                    reference: 'pass-through-sink',
                    output: LatticeLayerOutput::passThrough(),
                    promotions: [],
                ),
            );
        }

        /** @var array<int, LatticeLayer> $latticeLayerIndex */
        $latticeLayerIndex = [];

        foreach ($layers as $layer) {
            $layerId = (int) $layer->getKey();

            if ($layerId < 1) {
                throw new RuntimeException(
                    'Promotion layer must be persisted before building a lattice stack.',
                );
            }

            $latticeLayerIndex[$layerId] = $stackBuilder->addLayer(
                $this->latticeLayerFactory->make($layer),
            );
        }

        foreach ($layers as $layer) {
            $layerId = (int) $layer->getKey();
            $latticeLayer = $latticeLayerIndex[$layerId] ?? null;

            if (! $latticeLayer instanceof LatticeLayer) {
                throw new RuntimeException(
                    sprintf(
                        'Layer [%s] is missing from the lattice layer index.',
                        (string) $layer->reference,
                    ),
                );
            }

            $latticeLayer->output = $this->latticeLayerOutputFactory->make(
                $layer,
                $latticeLayerIndex,
                $passThroughLayer,
            );
        }

        $rootLayerModel = $this->resolveRootLayer($stack, $layers);
        $rootLayerId = (int) $rootLayerModel->getKey();
        $rootLayer = $latticeLayerIndex[$rootLayerId] ?? null;

        if (! $rootLayer instanceof LatticeLayer) {
            throw new RuntimeException(
                sprintf(
                    'Promotion stack root layer [%s] is missing from the lattice layer index.',
                    (string) $rootLayerModel->reference,
                ),
            );
        }

        $stackBuilder->setRoot($rootLayer);

        return $stackBuilder->build();
    }

    /**
     * @param  Collection<int, PromotionLayerModel>  $layers
     */
    private function resolveRootLayer(
        PromotionStackModel $stack,
        Collection $layers,
    ): PromotionLayerModel {
        $configuredRootReference = trim(
            (string) ($stack->root_layer_reference ?? ''),
        );

        if ($configuredRootReference === '') {
            return $layers->first();
        }

        $rootLayer = $layers->first(
            fn (PromotionLayerModel $layer): bool => $layer->reference ===
                $configuredRootReference,
        );

        if ($rootLayer instanceof PromotionLayerModel) {
            return $rootLayer;
        }

        throw new RuntimeException(
            sprintf(
                'Promotion stack root layer [%s] was not found.',
                $configuredRootReference,
            ),
        );
    }

    private function requiresPassThroughSink(PromotionLayerModel $layer): bool
    {
        if ($this->outputMode($layer) !== PromotionLayerOutputMode::Split) {
            return false;
        }

        return $this->targetMode($layer->participating_output_mode) ===
            PromotionLayerOutputTargetMode::PassThrough ||
            $this->targetMode($layer->non_participating_output_mode) ===
                PromotionLayerOutputTargetMode::PassThrough;
    }

    private function outputMode(
        PromotionLayerModel $layer,
    ): ?PromotionLayerOutputMode {
        $outputMode = $layer->output_mode;

        if ($outputMode instanceof PromotionLayerOutputMode) {
            return $outputMode;
        }

        if (
            $outputMode instanceof \BackedEnum &&
            is_string($outputMode->value)
        ) {
            return PromotionLayerOutputMode::tryFrom($outputMode->value);
        }

        if (is_string($outputMode)) {
            return PromotionLayerOutputMode::tryFrom($outputMode);
        }

        return null;
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
