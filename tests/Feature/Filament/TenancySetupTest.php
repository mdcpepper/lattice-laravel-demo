<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Tenancy\EditTeamProfile;
use App\Filament\Pages\Tenancy\RegisterTeam;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

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

it(
    'redirects to the admin panel after updating the team profile',
    function (): void {
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'name' => 'Original Team Name',
        ]);

        $team->members()->attach($user);

        Filament::setCurrentPanel('admin');
        Filament::setTenant($team, isQuiet: true);

        $this->actingAs($user);

        Livewire::test(EditTeamProfile::class)
            ->fillForm([
                'name' => 'Updated Team Name',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(Filament::getUrl($team));

        expect($team->fresh()->name)->toBe('Updated Team Name');
    },
);
