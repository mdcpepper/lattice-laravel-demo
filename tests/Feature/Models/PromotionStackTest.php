<?php

namespace Tests\Feature\Models;

use App\Models\PromotionStack;
use App\Models\Team;

it('activeForTeam returns the currently active stack', function (): void {
    $team = Team::factory()->create();
    $stack = PromotionStack::factory()->for($team)->active()->create();

    expect(PromotionStack::activeForTeam($team->id)?->id)->toBe($stack->id);
});

it('activeForTeam returns null when no stack is active', function (): void {
    $team = Team::factory()->create();
    PromotionStack::factory()->for($team)->create([
        'active_from' => '2025-01-01',
        'active_to' => '2025-06-30',
    ]);

    expect(PromotionStack::activeForTeam($team->id))->toBeNull();
});

it('activeForTeam does not return a stack from another team', function (): void {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    PromotionStack::factory()->for($otherTeam)->active()->create();

    expect(PromotionStack::activeForTeam($team->id))->toBeNull();
});

it('activeForTeam returns an open-ended stack as active', function (): void {
    $team = Team::factory()->create();
    $stack = PromotionStack::factory()->for($team)->create([
        'active_from' => now()->subDay()->toDateString(),
        'active_to' => null,
    ]);

    expect(PromotionStack::activeForTeam($team->id)?->id)->toBe($stack->id);
});

it('activeForTeam treats active_to as inclusive', function (): void {
    $team = Team::factory()->create();
    $stack = PromotionStack::factory()->for($team)->create([
        'active_from' => now()->subDay()->toDateString(),
        'active_to' => now()->toDateString(),
    ]);

    expect(PromotionStack::activeForTeam($team->id)?->id)->toBe($stack->id);
});
