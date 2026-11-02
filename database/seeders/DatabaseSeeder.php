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

        User::create([
            'first_name' => 'Test User',
            'last_name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('123456789'),
            'city' => 'casablanca',
            'country' => 'morocco',
            'phone_number' => '0612345678',
            'username' => 'testuser',
            'gender' => 'male'

        ]);

        $this->call([
            RoleSeeder::class,
            CasablancaVenueSeeder::class,
            TeamSeeder::class,
            VenueAvailabilitySeeder::class,
        ]);
    }
}
