<?php

namespace Database\Seeders;

use App\Models\Field;
use App\Models\FieldFormat;
use App\Models\FieldPrice;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class RealFieldSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Field Formats ─────────────────────────────────────────────────
        $formats = [
            ['name' => '5vs5',   'players_per_team' => 5,  'description' => 'Small-sided 5 vs 5 format'],
            ['name' => '6vs6',   'players_per_team' => 6,  'description' => 'Small-sided 6 vs 6 format'],
            ['name' => '7vs7',   'players_per_team' => 7,  'description' => 'Medium-sided 7 vs 7 format'],
            ['name' => '8vs8',   'players_per_team' => 8,  'description' => 'Medium-sided 8 vs 8 format'],
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

        // ── 2. Venues ────────────────────────────────────────────────────────
        $venuesData = [
            [
                'name'      => 'City Foot',
                'address'   => 'Rue Ibnou Majid, Quartier Racine',
                'city'      => 'Algiers',
                'phone'     => '+213 21 000 001',
                'latitude'  => 36.7372,
                'longitude' => 3.0865,
                'fields'    => [
                    ['name' => 'Field A', 'format' => '5vs5', 'surface_type' => 'artificial', 'is_indoor' => false, 'width_m' => 25, 'length_m' => 42],
                    ['name' => 'Field B', 'format' => '7vs7', 'surface_type' => 'artificial', 'is_indoor' => false, 'width_m' => 35, 'length_m' => 55],
                ],
            ],
            [
                'name'      => 'Arena Ville',
                'address'   => 'Boulevard National, Bab Ezzouar',
                'city'      => 'Algiers',
                'phone'     => '+213 21 000 002',
                'latitude'  => 36.7200,
                'longitude' => 3.1800,
                'fields'    => [
                    ['name' => 'Indoor 5v5', 'format' => '5vs5', 'surface_type' => 'futsal', 'is_indoor' => true,  'width_m' => 20, 'length_m' => 40],
                ],
            ],
            [
                'name'      => 'Green Sports Park',
                'address'   => 'Hay Hassani, Route Nationale',
                'city'      => 'Oran',
                'phone'     => '+213 41 000 003',
                'latitude'  => 35.6911,
                'longitude' => -0.6417,
                'fields'    => [
                    ['name' => 'Grass 11',  'format' => '11vs11', 'surface_type' => 'grass',      'is_indoor' => false, 'width_m' => 68, 'length_m' => 105],
                    ['name' => 'Grass 7v7', 'format' => '7vs7',   'surface_type' => 'grass',      'is_indoor' => false, 'width_m' => 35, 'length_m' => 55],
                    ['name' => 'Futsal A',  'format' => '5vs5',   'surface_type' => 'futsal',     'is_indoor' => true,  'width_m' => 20, 'length_m' => 38],
                ],
            ],
            [
                'name'      => 'Atlantique Club',
                'address'   => 'Corniche Ouest',
                'city'      => 'Oran',
                'phone'     => '+213 41 000 004',
                'latitude'  => 35.7080,
                'longitude' => -0.6200,
                'fields'    => [
                    ['name' => 'Pitch 1', 'format' => '5vs5', 'surface_type' => 'artificial', 'is_indoor' => false, 'width_m' => 25, 'length_m' => 42],
                    ['name' => 'Pitch 2', 'format' => '5vs5', 'surface_type' => 'artificial', 'is_indoor' => false, 'width_m' => 25, 'length_m' => 42],
                ],
            ],
            [
                'name'      => 'Stade El Mansour',
                'address'   => 'Rue des Frères Bouadou, Birtouta',
                'city'      => 'Algiers',
                'phone'     => '+213 21 000 005',
                'latitude'  => 36.6833,
                'longitude' => 3.0500,
                'fields'    => [
                    ['name' => 'Main Pitch',   'format' => '11vs11', 'surface_type' => 'grass',      'is_indoor' => false, 'width_m' => 68, 'length_m' => 105],
                    ['name' => 'Training 7v7', 'format' => '7vs7',   'surface_type' => 'artificial', 'is_indoor' => false, 'width_m' => 35, 'length_m' => 55],
                    ['name' => 'Mini Foot A',  'format' => '5vs5',   'surface_type' => 'artificial', 'is_indoor' => false, 'width_m' => 25, 'length_m' => 42],
                ],
            ],
            [
                'name'      => 'Complex Sportif Ibn Badis',
                'address'   => 'Route de Skikda, El Khroub',
                'city'      => 'Constantine',
                'phone'     => '+213 31 000 006',
                'latitude'  => 36.2765,
                'longitude' => 6.6147,
                'fields'    => [
                    ['name' => 'Field 1',    'format' => '7vs7',   'surface_type' => 'artificial', 'is_indoor' => false, 'width_m' => 35, 'length_m' => 55],
                    ['name' => 'Field 2',    'format' => '7vs7',   'surface_type' => 'artificial', 'is_indoor' => false, 'width_m' => 35, 'length_m' => 55],
                    ['name' => 'Futsal Box', 'format' => '5vs5',   'surface_type' => 'futsal',     'is_indoor' => true,  'width_m' => 20, 'length_m' => 40],
                ],
            ],
            [
                'name'      => 'Sport Zone Annaba',
                'address'   => 'Cité El Bouni, Route Nationale 44',
                'city'      => 'Annaba',
                'phone'     => '+213 38 000 007',
                'latitude'  => 36.9000,
                'longitude' => 7.7667,
                'fields'    => [
                    ['name' => 'Terrain A', 'format' => '5vs5', 'surface_type' => 'artificial', 'is_indoor' => false, 'width_m' => 25, 'length_m' => 42],
                    ['name' => 'Terrain B', 'format' => '5vs5', 'surface_type' => 'artificial', 'is_indoor' => false, 'width_m' => 25, 'length_m' => 42],
                    ['name' => 'Terrain C', 'format' => '7vs7', 'surface_type' => 'grass',      'is_indoor' => false, 'width_m' => 35, 'length_m' => 55],
                ],
            ],
            [
                'name'      => 'Tlemcen Football Arena',
                'address'   => 'Quartier Boudghene, Avenue Colonel Lotfi',
                'city'      => 'Tlemcen',
                'phone'     => '+213 43 000 008',
                'latitude'  => 34.8828,
                'longitude' => -1.3167,
                'fields'    => [
                    ['name' => 'Indoor Futsal 1', 'format' => '5vs5', 'surface_type' => 'futsal', 'is_indoor' => true, 'width_m' => 20, 'length_m' => 40],
                    ['name' => 'Indoor Futsal 2', 'format' => '5vs5', 'surface_type' => 'futsal', 'is_indoor' => true, 'width_m' => 20, 'length_m' => 40],
                ],
            ],
            [
                'name'      => 'Sétif Sports Hub',
                'address'   => 'Cité OPGI, Route de Béjaïa',
                'city'      => 'Sétif',
                'phone'     => '+213 36 000 009',
                'latitude'  => 36.1898,
                'longitude' => 5.4108,
                'fields'    => [
                    ['name' => 'Pitch Alpha',   'format' => '5vs5',   'surface_type' => 'artificial', 'is_indoor' => false, 'width_m' => 25, 'length_m' => 42],
                    ['name' => 'Pitch Beta',    'format' => '7vs7',   'surface_type' => 'artificial', 'is_indoor' => false, 'width_m' => 35, 'length_m' => 55],
                    ['name' => 'Full Grass 11', 'format' => '11vs11', 'surface_type' => 'grass',      'is_indoor' => false, 'width_m' => 68, 'length_m' => 105],
                ],
            ],
            [
                'name'      => 'Béjaïa Coast Arena',
                'address'   => 'Route de Tichy, Béjaïa',
                'city'      => 'Béjaïa',
                'phone'     => '+213 34 000 010',
                'latitude'  => 36.7509,
                'longitude' => 5.0564,
                'fields'    => [
                    ['name' => 'Mini Field 1', 'format' => '5vs5', 'surface_type' => 'artificial', 'is_indoor' => false, 'width_m' => 25, 'length_m' => 42],
                    ['name' => 'Mini Field 2', 'format' => '5vs5', 'surface_type' => 'artificial', 'is_indoor' => false, 'width_m' => 25, 'length_m' => 42],
                ],
            ],
        ];

        // ── 3. Cover images (one per venue, cycled) ──────────────────────────
        $coverImages = [
            'https://images.unsplash.com/photo-1575361204480-aadea25e6e68?w=800&q=80',
            'https://images.unsplash.com/photo-1459865264687-595d652de67e?w=800&q=80',
            'https://images.unsplash.com/photo-1431324155629-1a6deb1dec8d?w=800&q=80',
            'https://images.unsplash.com/photo-1518604666860-9ed391f76460?w=800&q=80',
            'https://images.unsplash.com/photo-1529900748604-07564a03e7a6?w=800&q=80',
            'https://images.unsplash.com/photo-1504016798967-5fde2e19e1dc?w=800&q=80',
            'https://images.unsplash.com/photo-1543326727-cf6c39e8f84c?w=800&q=80',
            'https://images.unsplash.com/photo-1551958219-acbc595d9e4c?w=800&q=80',
            'https://images.unsplash.com/photo-1610201429367-b92b47c09797?w=800&q=80',
            'https://images.unsplash.com/photo-1486286701208-1d58e9338013?w=800&q=80',
        ];

        // ── 4. Seed Venues + Fields + Prices ─────────────────────────────────
        foreach ($venuesData as $venueIndex => $venueData) {
            $fieldsData = $venueData['fields'];
            unset($venueData['fields']);

            $venue = Venue::firstOrCreate(
                ['name' => $venueData['name']],
                array_merge($venueData, ['is_active' => true])
            );

            foreach ($fieldsData as $fieldData) {
                $formatKey  = $fieldData['format'];
                unset($fieldData['format']);

                $field = Field::firstOrCreate(
                    ['venue_id' => $venue->id, 'name' => $fieldData['name']],
                    array_merge($fieldData, [
                        'field_format_id' => $formatMap[$formatKey]->id,
                        'is_active'       => true,
                    ])
                );

                // Skip pricing if already seeded
                if ($field->prices()->exists()) {
                    continue;
                }

                // ── Default fallback price (priority 1) ───────────────────
                FieldPrice::create([
                    'field_id'     => $field->id,
                    'label'        => 'Default',
                    'pricing_type' => 'hourly',
                    'price'        => 800.00,
                    'currency'     => 'MAD',
                    'priority'     => 1,
                    'slot_duration' => 60,
                    'is_active'    => true,
                ]);

                // ── Weekend price (priority 2) ────────────────────────────
                foreach (['friday', 'saturday'] as $day) {
                    FieldPrice::create([
                        'field_id'     => $field->id,
                        'label'        => 'Weekend',
                        'pricing_type' => 'hourly',
                        'day_of_week'  => $day,
                        'price'        => 1000.00,
                        'currency'     => 'MAD',
                        'priority'     => 2,
                        'slot_duration' => 60,
                        'is_active'    => true,
                    ]);
                }

                // ── Peak hours price (priority 3) ─────────────────────────
                FieldPrice::create([
                    'field_id'     => $field->id,
                    'label'        => 'Peak Hours',
                    'pricing_type' => 'hourly',
                    'start_time'   => '18:00:00',
                    'end_time'     => '22:00:00',
                    'price'        => 1200.00,
                    'currency'     => 'MAD',
                    'priority'     => 3,
                    'slot_duration' => 60,
                    'is_active'    => true,
                ]);
            }

            // ── Seed cover image via Spatie ───────────────────────────────
            try {
                $imageUrl   = $coverImages[$venueIndex % count($coverImages)];
                $firstField = $venue->fields()->first();
                if ($firstField && ! $firstField->hasMedia('cover')) {
                    $firstField->addMediaFromUrl($imageUrl)->toMediaCollection('cover');
                }
            } catch (\Exception $e) {
                // skip if URL unreachable
            }
        }
    }
}
