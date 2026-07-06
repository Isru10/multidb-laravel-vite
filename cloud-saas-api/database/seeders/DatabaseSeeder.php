<?php

namespace Database\Seeders;

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
        // User::factory(10)->create();
        // Replace with the exact Gmail address you will use to log in via Google
        User::updateOrCreate(
            ['email' => 'gloryzone0@gmail.com'],
            [
                'name' => 'Super Admin',
                'is_super_admin' => true,
                'provider_name' => 'google',
            ]
        );

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
