<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public const DEFAULT_TEAM_NAME = 'Default';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $team = Team::query()->firstOrCreate([
            'name' => self::DEFAULT_TEAM_NAME,
        ]);

        $generatedPassword = Str::password(24);

        $adminUser = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'admin',
                'password' => Hash::make($generatedPassword),
            ],
        );

        $team->members()->syncWithoutDetaching([$adminUser->id]);

        $this->command?->warn('Default admin account created/updated:');
        $this->command?->line('  username: admin');
        $this->command?->line('  email: admin@example.com');
        $this->command?->line("  password: {$generatedPassword}");

        $this->call(ProductSeeder::class);
        $this->call(CustomerSeeder::class);
        $this->call(CartSeeder::class);
    }
}
