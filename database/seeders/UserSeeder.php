<?php

namespace Database\Seeders;

use App\Models\PlayerStat;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory(100)->create()->each(
            fn($user) =>
            PlayerStat::factory()->create(['user_id' => $user->id])
        );
    }
}
