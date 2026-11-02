<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name'         => 'admin',
                'display_name' => 'Administrator',
                'description'  => 'Full access to the dashboard and all management features.',
            ],
            [
                'name'         => 'venue_owner',
                'display_name' => 'Venue Owner',
                'description'  => 'Owns and manages one or more venues.',
            ],
            [
                'name'         => 'player',
                'display_name' => 'Player',
                'description'  => 'Regular player who can book fields and join teams.',
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }
    }
}
