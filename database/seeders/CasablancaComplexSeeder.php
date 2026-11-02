<?php

namespace Database\Seeders;

use App\Models\Field;
use App\Models\FieldFormat;
use App\Models\FieldPrice;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class CasablancaComplexSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Field formats ────────────────────────────────────────────────
        $formats = [
            ['name' => '5vs5',   'players_per_team' => 5,  'description' => 'Small-sided 5 vs 5 format'],
            ['name' => '6vs6',   'players_per_team' => 6,  'description' => 'Small-sided 6 vs 6 format'],
            ['name' => '7vs7',   'players_per_team' => 7,  'description' => 'Medium-sided 7 vs 7 format'],
            ['name' => '9vs9',   'players_per_team' => 9,  'description' => 'Medium-sided 9 vs 9 format'],
            ['name' => '11vs11', 'players_per_team' => 11, 'description' => 'Full 11 vs 11 format'],
        ];

        $formatMap = [];
        foreach ($formats as $f) {
            $formatMap[$f['name']] = FieldFormat::firstOrCreate(
                ['name' => $f['name']],
                ['players_per_team' => $f['players_per_team'], 'description' => $f['description']]
            );
        }

        // Dimensions and base hourly price (MAD) per format, shared by every complex.
        $formatSpecs = [
            '5vs5'   => ['width_m' => 25, 'length_m' => 42,  'price' => 250],
            '6vs6'   => ['width_m' => 30, 'length_m' => 50,  'price' => 300],
            '7vs7'   => ['width_m' => 35, 'length_m' => 55,  'price' => 350],
            '9vs9'   => ['width_m' => 50, 'length_m' => 80,  'price' => 450],
            '11vs11' => ['width_m' => 68, 'length_m' => 105, 'price' => 600],
        ];

        // ── 2. Complexes (venues) ───────────────────────────────────────────
        $venuesData = [
            [
                'name'        => 'Five Stars Maarif',
                'description' => 'Complexe de football situé à Maarif, Casablanca.',
                'address'     => '12, Boulevard Zerktouni, Maarif',
                'city'        => 'Casablanca',
                'country'     => 'Morocco',
                'phone'       => '+212522000001',
                'latitude'    => 33.5783,
                'longitude'   => -7.6325,
            ],
            [
                'name'        => 'Atlas Arena Ain Diab',
                'description' => 'Complexe de football situé à Ain Diab, Casablanca.',
                'address'     => '45, Boulevard de la Corniche, Ain Diab',
                'city'        => 'Casablanca',
                'country'     => 'Morocco',
                'phone'       => '+212522000002',
                'latitude'    => 33.5950,
                'longitude'   => -7.6900,
            ],
            [
                'name'        => 'Golden Goal Anfa',
                'description' => 'Complexe de football situé à Anfa, Casablanca.',
                'address'     => "78, Boulevard d'Anfa, Anfa",
                'city'        => 'Casablanca',
                'country'     => 'Morocco',
                'phone'       => '+212522000003',
                'latitude'    => 33.5900,
                'longitude'   => -7.6500,
            ],
            [
                'name'        => 'Champions Park Sidi Maarouf',
                'description' => 'Complexe de football situé à Sidi Maarouf, Casablanca.',
                'address'     => '9, Route de Sidi Maarouf, Sidi Maarouf',
                'city'        => 'Casablanca',
                'country'     => 'Morocco',
                'phone'       => '+212522000004',
                'latitude'    => 33.5100,
                'longitude'   => -7.6300,
            ],
            [
                'name'        => 'Ocean Foot Ain Sebaa',
                'description' => 'Complexe de football situé à Ain Sebaa, Casablanca.',
                'address'     => '33, Avenue des FAR, Ain Sebaa',
                'city'        => 'Casablanca',
                'country'     => 'Morocco',
                'phone'       => '+212522000005',
                'latitude'    => 33.6100,
                'longitude'   => -7.5300,
            ],
        ];

        // ── 3. Seed venues + 5 fields (one per format) + hourly price ───────
        foreach ($venuesData as $venueData) {
            $venue = Venue::firstOrCreate(
                ['name' => $venueData['name']],
                array_merge($venueData, ['is_active' => true])
            );

            foreach ($formatSpecs as $formatKey => $spec) {
                $field = Field::firstOrCreate(
                    ['venue_id' => $venue->id, 'name' => "Terrain {$formatKey}"],
                    [
                        'field_format_id' => $formatMap[$formatKey]->id,
                        'surface_type'    => 'artificial',
                        'width_m'         => $spec['width_m'],
                        'length_m'        => $spec['length_m'],
                        'is_indoor'       => false,
                        'is_active'       => true,
                    ]
                );

                if ($field->prices()->exists()) {
                    continue;
                }

                FieldPrice::create([
                    'field_id'      => $field->id,
                    'label'         => 'Tarif Standard',
                    'pricing_type'  => 'hourly',
                    'price'         => $spec['price'],
                    'currency'      => 'MAD',
                    'priority'      => 1,
                    'slot_duration' => 60,
                    'is_active'     => true,
                ]);
            }
        }

        $this->command->info('Seeded 5 Casablanca complexes with 5 fields each (5vs5, 6vs6, 7vs7, 9vs9, 11vs11) and prices.');
    }
}
