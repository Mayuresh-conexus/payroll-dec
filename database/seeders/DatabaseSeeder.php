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

        $this->call([
        EmployeeSeeder::class,
    ]);
    
        // User::factory(10)->create();
        // default test user
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // admin user for initial setup
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            // UserFactory uses the hashed default password 'password'
        ]);
    }
}
