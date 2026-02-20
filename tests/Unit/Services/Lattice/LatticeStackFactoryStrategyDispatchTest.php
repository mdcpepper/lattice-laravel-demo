<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Lattice;

use App\Models\Promotions\PromotionStack;
use App\Services\Lattice\Stacks\LatticeStackFactory;
use Lattice\Stack\Stack as LatticeStack;
use RuntimeException;
use Tests\Helpers\FakeLatticeStackStrategy;

test(
    'delegates lattice stack building to the first supporting strategy',
    function (): void {
        $stack = new PromotionStack(['name' => 'Checkout Stack']);

        $nonSupportingStrategy = new FakeLatticeStackStrategy(
            supports: fn (PromotionStack $stack): bool => false,
            make: fn (PromotionStack $stack): LatticeStack => fakeLatticeStack(),
        );

        $supportingStrategy = new FakeLatticeStackStrategy(
            supports: fn (PromotionStack $stack): bool => true,
            make: fn (PromotionStack $stack): LatticeStack => fakeLatticeStack(),
        );

        $factory = new LatticeStackFactory([
            $nonSupportingStrategy,
            $supportingStrategy,
        ]);

        $result = $factory->make($stack);

        expect($result)
            ->toBeInstanceOf(LatticeStack::class)
            ->and($nonSupportingStrategy->builtStackNames)
            ->toBeEmpty()
            ->and($supportingStrategy->builtStackNames)
            ->toBe(['Checkout Stack']);
    },
);

test('throws when no strategy supports the stack', function (): void {
    $stack = new PromotionStack(['name' => 'Unsupported Stack']);

    $factory = new LatticeStackFactory([
        new FakeLatticeStackStrategy(
            supports: fn (PromotionStack $stack): bool => false,
            make: fn (PromotionStack $stack): LatticeStack => fakeLatticeStack(),
        ),
    ]);

    expect(fn (): LatticeStack => $factory->make($stack))->toThrow(
        RuntimeException::class,
        "Unsupported stack type [App\Models\Promotions\PromotionStack].",
    );
});

function fakeLatticeStack(): LatticeStack
{
    return new LatticeStack(layers: []);
}
