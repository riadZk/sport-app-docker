<?php

namespace Database\Seeders;

use App\Models\SportCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SportCategorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Football',
            'Basketball',
            'Tennis',
            'Volleyball',
            'Swimming',
            'Running',
            'Cycling',
            'Boxing',
            'Martial Arts',
            'Golf',
            'Padel',
            'Rugby',
            'Handball',
            'Badminton',
            'Table Tennis',
        ];

        foreach ($categories as $category) {
            SportCategory::firstOrCreate(['name' => $category]);
        }
    }
}
