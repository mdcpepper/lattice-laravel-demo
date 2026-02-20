<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Lattice;

use App\Models\PromotionLayer;
use App\Services\Lattice\Stacks\LatticeLayerOutputFactory;
use Lattice\Stack\Layer as LatticeLayer;
use Lattice\Stack\LayerOutput as LatticeLayerOutput;
use RuntimeException;
use Tests\Helpers\FakeLatticeLayerOutputStrategy;

test(
    'delegates lattice layer output building to the first supporting strategy',
    function (): void {
        $layer = new PromotionLayer([
            'reference' => 'root',
            'output_mode' => 'pass_through',
        ]);

        $nonSupportingStrategy = new FakeLatticeLayerOutputStrategy(
            supports: fn (PromotionLayer $layer): bool => false,
            make: fn (
                PromotionLayer $layer,
                array $latticeLayerIndex,
                ?LatticeLayer $passThroughLayer,
            ): LatticeLayerOutput => LatticeLayerOutput::passThrough(),
        );

        $supportingStrategy = new FakeLatticeLayerOutputStrategy(
            supports: fn (PromotionLayer $layer): bool => true,
            make: fn (
                PromotionLayer $layer,
                array $latticeLayerIndex,
                ?LatticeLayer $passThroughLayer,
            ): LatticeLayerOutput => LatticeLayerOutput::passThrough(),
        );

        $factory = new LatticeLayerOutputFactory([
            $nonSupportingStrategy,
            $supportingStrategy,
        ]);

        $result = $factory->make($layer, []);

        expect($result)
            ->toBeInstanceOf(LatticeLayerOutput::class)
            ->and($nonSupportingStrategy->builtLayerReferences)
            ->toBeEmpty()
            ->and($supportingStrategy->builtLayerReferences)
            ->toBe(['root']);
    },
);

test(
    'throws when no strategy supports the layer output mode',
    function (): void {
        $layer = new PromotionLayer([
            'reference' => 'root',
            'output_mode' => null,
        ]);

        $factory = new LatticeLayerOutputFactory([
            new FakeLatticeLayerOutputStrategy(
                supports: fn (PromotionLayer $layer): bool => false,
                make: fn (
                    PromotionLayer $layer,
                    array $latticeLayerIndex,
                    ?LatticeLayer $passThroughLayer,
                ): LatticeLayerOutput => LatticeLayerOutput::passThrough(),
            ),
        ]);

        expect(fn (): LatticeLayerOutput => $factory->make($layer, []))->toThrow(
            RuntimeException::class,
            'Unsupported layer output mode [null].',
        );
    },
);
