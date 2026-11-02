<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Field;
use App\Models\FieldFormat;
use App\Models\FieldPrice;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueReview;
use Faker\Factory as Faker;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CasablancaVenueSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Toggle downloading + attaching images to the media library. */
    private bool $withImages = true;

    /** Casablanca districts with an approximate center (lat, lng). */
    private array $districts = [
        'Maarif'         => [33.5783, -7.6325],
        'Ain Diab'       => [33.5950, -7.6900],
        'Anfa'           => [33.5900, -7.6500],
        'Oasis'          => [33.5550, -7.6300],
        'Californie'     => [33.5400, -7.6200],
        'Hay Hassani'    => [33.5600, -7.6700],
        'Sidi Maarouf'   => [33.5100, -7.6300],
        'Bourgogne'      => [33.5950, -7.6350],
        'Ain Sebaa'      => [33.6100, -7.5300],
        'Sidi Bernoussi' => [33.6200, -7.5000],
        'Derb Sultan'    => [33.5750, -7.6050],
        'Roches Noires'  => [33.6000, -7.5700],
    ];

    private array $namePrefixes = [
        'Five Stars',
        'Green Field',
        'Atlas',
        'Golden Goal',
        'Stade',
        'City Foot',
        'Mega Foot',
        'Pro Soccer',
        'Arena',
        'Champions',
        'Victory',
        'Elite Foot',
        'Casa',
        'Royal Pitch',
        'Ocean',
        'Star Foot',
        'Dribble',
        'Top Score',
        'Penalty',
        'Corner',
        'Galaxy Foot',
        'Phoenix',
        'Tiki Taka',
        'Kick Off',
    ];

    private array $streets = [
        'Boulevard Zerktouni',
        'Rue Ibnou Mounir',
        'Boulevard d\'Anfa',
        'Boulevard de la Corniche',
        'Rue Abou Bakr Seddik',
        'Boulevard Ghandi',
        'Avenue Hassan II',
        'Boulevard Moulay Youssef',
        'Rue de Berne',
        'Boulevard Mohamed Zerktouni',
        'Avenue des FAR',
        'Boulevard Bir Anzarane',
        'Rue Soumaya',
        'Boulevard Yacoub El Mansour',
        'Route d\'El Jadida',
    ];

    /** Stable football-pitch images (Unsplash CDN). */
    private array $footballImages = [
        'https://images.unsplash.com/photo-1551958219-acbc608c6377?w=1200&q=80',
        'https://images.unsplash.com/photo-1574629810360-7efbbe195018?w=1200&q=80',
        'https://images.unsplash.com/photo-1529900748604-07564a03e7a6?w=1200&q=80',
        'https://images.unsplash.com/photo-1459865264687-595d652de67e?w=1200&q=80',
        'https://images.unsplash.com/photo-1560272564-c83b66b1ad12?w=1200&q=80',
        'https://images.unsplash.com/photo-1577223625816-7546f13df25d?w=1200&q=80',
        'https://images.unsplash.com/photo-1486286701208-1d58e9338013?w=1200&q=80',
    ];

    public function run(): void
    {
        $faker = Faker::create('fr_FR');

        // ── 1. Field formats ────────────────────────────────────────────────
        $formats = [
            '5vs5'   => FieldFormat::firstOrCreate(['name' => '5vs5'], ['players_per_team' => 5, 'description' => 'Small-sided 5 vs 5']),
            '6vs6'   => FieldFormat::firstOrCreate(['name' => '6vs6'], ['players_per_team' => 6, 'description' => 'Small-sided 6 vs 6']),
            '7vs7'   => FieldFormat::firstOrCreate(['name' => '7vs7'], ['players_per_team' => 7, 'description' => 'Medium-sided 7 vs 7']),
            '8vs8'   => FieldFormat::firstOrCreate(['name' => '8vs8'], ['players_per_team' => 8, 'description' => 'Medium-sided 8 vs 8']),
            '9vs9'   => FieldFormat::firstOrCreate(['name' => '9vs9'], ['players_per_team' => 9, 'description' => 'Medium-sided 9 vs 9']),
            '11vs11' => FieldFormat::firstOrCreate(['name' => '11vs11'], ['players_per_team' => 11, 'description' => 'Full 11 vs 11']),
        ];

        // ── 2. Amenities ────────────────────────────────────────────────────
        $amenityNames = [
            'Parking'        => 'parking',
            'Wi-Fi'          => 'wifi',
            'Locker Rooms'   => 'lockers',
            'Showers'        => 'shower',
            'Café'           => 'coffee',
            'Lighting'       => 'lightbulb',
            'Security'       => 'shield',
            'Seating Area'   => 'chair',
            'First Aid'      => 'medkit',
            'Drinking Water' => 'water',
            'Equipment Rent' => 'football',
            'Restrooms'      => 'restroom',
        ];

        $amenities = collect($amenityNames)->map(
            fn($icon, $name) => Amenity::firstOrCreate(['name' => $name], ['icon' => $icon])
        )->values();

        // ── 3. Reviewer users ───────────────────────────────────────────────
        $users = collect();
        for ($i = 0; $i < 40; $i++) {
            $users->push(User::create([
                'first_name'   => $faker->firstName(),
                'last_name'    => $faker->lastName(),
                'username'     => Str::lower(Str::random(10)),
                'phone_number' => '+2126' . $faker->unique()->numerify('########'),
                'email'        => $faker->unique()->safeEmail(),
                'password'     => bcrypt('password'),
                'city'         => 'Casablanca',
                'country'      => 'Morocco',
                'gender'       => $faker->randomElement(['male', 'female']),
            ]));
        }

        $reviewComments = [
            'Excellent terrain, bien entretenu.',
            'Bon rapport qualité-prix, je recommande.',
            'Pelouse en bon état mais parking limité.',
            'Super ambiance, vestiaires propres.',
            'Éclairage parfait pour les matchs du soir.',
            'Accueil chaleureux, je reviendrai.',
            'Terrain correct, douches un peu vieilles.',
            'Très bonne expérience avec mes amis.',
            'Réservation facile et personnel sympa.',
            'Le gazon synthétique est de bonne qualité.',
        ];

        $districtKeys = array_keys($this->districts);

        // ── 4. Venues ───────────────────────────────────────────────────────
        for ($i = 0; $i < 100; $i++) {
            $district = $districtKeys[$i % count($districtKeys)];
            [$baseLat, $baseLng] = $this->districts[$district];

            $name = $this->namePrefixes[$i % count($this->namePrefixes)] . ' ' . $district
                . ' ' . Str::upper(Str::random(3));

            $venue = Venue::create([
                'name'        => $name,
                'description' => "Complexe de football situé à {$district}, Casablanca. "
                    . 'Terrains en gazon synthétique, vestiaires équipés et éclairage nocturne. '
                    . 'Ouvert tous les jours de 08h00 à 00h00.',
                'address'     => $faker->numberBetween(1, 250) . ', '
                    . $this->streets[array_rand($this->streets)] . ', ' . $district,
                'city'        => 'Casablanca',
                'phone'       => '+2125' . $faker->numerify('22######'),
                'latitude'    => round($baseLat + $faker->randomFloat(4, -0.012, 0.012), 8),
                'longitude'   => round($baseLng + $faker->randomFloat(4, -0.012, 0.012), 8),
                'is_active'   => true,
            ]);

            // ── 4a. Amenities (4–8 random) ──────────────────────────────────
            $venue->amenities()->attach(
                $amenities->random($faker->numberBetween(4, 8))->pluck('id')->all()
            );

            // ── 4b. Two fields per venue, each with price rules ─────────────
            for ($f = 1; $f <= 2; $f++) {
                $formatKey = $faker->randomElement(['5vs5', '7vs7', '11vs11']);
                $surface   = $faker->randomElement(['artificial', 'artificial', 'grass', 'futsal']);
                $isIndoor  = $surface === 'futsal' ? $faker->boolean(70) : false;

                [$w, $l] = match ($formatKey) {
                    '5vs5'   => [25, 42],
                    '7vs7'   => [35, 55],
                    '11vs11' => [68, 105],
                };

                $field = Field::create([
                    'venue_id'        => $venue->id,
                    'field_format_id' => $formats[$formatKey]->id,
                    'name'            => "Terrain {$f}",
                    'surface_type'    => $surface,
                    'width_m'         => $w,
                    'length_m'        => $l,
                    'is_indoor'       => $isIndoor,
                    'is_active'       => true,
                ]);

                $basePrice = $faker->numberBetween(200, 500);

                // Weekday standard rate
                FieldPrice::create([
                    'field_id'      => $field->id,
                    'label'         => 'Tarif Semaine',
                    'pricing_type'  => 'hourly',
                    'price'         => $basePrice,
                    'currency'      => 'MAD',
                    'priority'      => 1,
                    'slot_duration' => 60,
                    'is_active'     => true,
                ]);

                // Weekend / peak rate
                FieldPrice::create([
                    'field_id'      => $field->id,
                    'label'         => 'Tarif Weekend',
                    'pricing_type'  => 'hourly',
                    'day_of_week'   => 'saturday',
                    'start_time'    => '18:00:00',
                    'end_time'      => '23:00:00',
                    'price'         => $basePrice + $faker->numberBetween(50, 150),
                    'currency'      => 'MAD',
                    'priority'      => 5,
                    'slot_duration' => 60,
                    'is_active'     => true,
                ]);
            }

            // ── 4c. Reviews / rating ────────────────────────────────────────
            $reviewers = $users->random($faker->numberBetween(3, 15));
            foreach ($reviewers as $reviewer) {
                VenueReview::create([
                    'venue_id' => $venue->id,
                    'user_id'  => $reviewer->id,
                    'rating'   => $faker->randomElement([3, 4, 4, 5, 5, 5]),
                    'comment'  => $faker->randomElement($reviewComments),
                ]);
            }

            // ── 4d. Images in the media library ─────────────────────────────
            if ($this->withImages) {
                $this->attachImages($venue, $faker);
            }
        }

        $this->command->info('Seeded 100 Casablanca venues with fields, prices, reviews and amenities.');
    }

    private function attachImages(Venue $venue, $faker): void
    {
        $images = $faker->randomElements(
            $this->footballImages,
            $faker->numberBetween(4, 5)
        );

        // First image → cover, the rest → gallery.
        $cover = array_shift($images);

        try {
            $venue->addMediaFromUrl($cover)->toMediaCollection('cover');
        } catch (\Throwable $e) {
            $this->command->warn("Cover image failed for venue {$venue->id}: {$e->getMessage()}");
        }

        foreach ($images as $url) {
            try {
                $venue->addMediaFromUrl($url)->toMediaCollection('gallery');
            } catch (\Throwable $e) {
                $this->command->warn("Gallery image failed for venue {$venue->id}: {$e->getMessage()}");
            }
        }
    }
}
