<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Tenancy\EditTeamProfile;
use App\Filament\Pages\Tenancy\RegisterTeam;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

it('returns only attached teams as user tenants', function (): void {
    $user = User::factory()->create();
    $memberTeam = Team::factory()->create();
    $otherTeam = Team::factory()->create();

    $memberTeam->members()->attach($user);

    expect($user->getTenants(Filament::getPanel('admin'))->pluck('id')->all())
        ->toBe([$memberTeam->id])
        ->and($user->canAccessTenant($memberTeam))
        ->toBeTrue()
        ->and($user->canAccessTenant($otherTeam))
        ->toBeFalse();
});

it('configures the admin panel for team tenancy pages', function (): void {
    $panel = Filament::getPanel('admin');

    expect($panel->getTenantModel())
        ->toBe(Team::class)
        ->and($panel->getTenantRegistrationPage())
        ->toBe(RegisterTeam::class)
        ->and($panel->getTenantProfilePage())
        ->toBe(EditTeamProfile::class);
});
