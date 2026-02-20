<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Lattice;

use App\Models\Promotions\PromotionLayer;
use App\Services\Lattice\Stacks\LatticeLayerFactory;
use Lattice\Stack\Layer as LatticeLayer;
use Lattice\Stack\LayerOutput;
use RuntimeException;
use Tests\Helpers\FakeLatticeLayerStrategy;

test(
    'delegates lattice layer building to the first supporting strategy',
    function (): void {
        $layer = new PromotionLayer([
            'reference' => 'root',
            'name' => 'Root Layer',
        ]);

        $nonSupportingStrategy = new FakeLatticeLayerStrategy(
            supports: fn (PromotionLayer $layer): bool => false,
            make: fn (PromotionLayer $layer): LatticeLayer => fakeLatticeLayer(
                $layer,
            ),
        );

        $supportingStrategy = new FakeLatticeLayerStrategy(
            supports: fn (PromotionLayer $layer): bool => true,
            make: fn (PromotionLayer $layer): LatticeLayer => fakeLatticeLayer(
                $layer,
            ),
        );

        $factory = new LatticeLayerFactory([
            $nonSupportingStrategy,
            $supportingStrategy,
        ]);

        $result = $factory->make($layer);

        expect($result)
            ->toBeInstanceOf(LatticeLayer::class)
            ->and($nonSupportingStrategy->builtLayerReferences)
            ->toBeEmpty()
            ->and($supportingStrategy->builtLayerReferences)
            ->toBe(['root']);
    },
);

test('throws when no strategy supports the layer', function (): void {
    $layer = new PromotionLayer([
        'reference' => 'unsupported-layer',
    ]);

    $factory = new LatticeLayerFactory([
        new FakeLatticeLayerStrategy(
            supports: fn (PromotionLayer $layer): bool => false,
            make: fn (PromotionLayer $layer): LatticeLayer => fakeLatticeLayer(
                $layer,
            ),
        ),
    ]);

    expect(fn (): LatticeLayer => $factory->make($layer))->toThrow(
        RuntimeException::class,
        "Unsupported layer type [App\Models\Promotions\PromotionLayer].",
    );
});

function fakeLatticeLayer(PromotionLayer $layer): LatticeLayer
{
    return new LatticeLayer(
        reference: $layer,
        output: LayerOutput::passThrough(),
        promotions: [],
    );
}
