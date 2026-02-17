<?php

declare(strict_types=1);

namespace App\Services\Lattice\Stacks;

use App\Models\PromotionLayer as PromotionLayerModel;
use Lattice\Layer as LatticeLayer;
use RuntimeException;

class LatticeLayerFactory
{
    /**
     * @param  array<int, LatticeLayerStrategy>  $latticeLayerStrategies
     */
    public function __construct(
        private readonly array $latticeLayerStrategies,
    ) {}

    public function make(PromotionLayerModel $layer): LatticeLayer
    {
        $strategy = collect($this->latticeLayerStrategies)->first(
            fn (LatticeLayerStrategy $strategy): bool => $strategy->supports(
                $layer,
            ),
        );

        if (! $strategy instanceof LatticeLayerStrategy) {
            throw $this->unsupportedLayerType($layer);
        }

        return $strategy->make($layer);
    }

    private function unsupportedLayerType(
        PromotionLayerModel $layer,
    ): RuntimeException {
        return new RuntimeException(
            sprintf('Unsupported layer type [%s].', get_debug_type($layer)),
        );
    }
}
