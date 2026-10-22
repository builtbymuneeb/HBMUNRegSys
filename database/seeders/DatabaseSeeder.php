<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CommitteeSeeder::class);

        User::factory()->role(UserRole::Registrations)->create([
            'name' => 'Registrations Team',
            'email' => 'registrations@example.com',
        ]);

        User::factory()->role(UserRole::Admin)->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);
    }
}
