<?php

namespace Database\Seeders;

use App\Models\Venue;
use App\Models\VenueAvailability;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class VenueAvailabilitySeeder extends Seeder
{
    /**
     * Give every venue a daily availability of 08:00 -> 23:00,
     * year-round (no seasonal window).
     */
    public function run(): void
    {
        $days = [
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
            'sunday',
        ];

        Venue::query()->chunkById(100, function ($venues) use ($days) {
            foreach ($venues as $venue) {
                foreach ($days as $day) {
                    VenueAvailability::updateOrCreate(
                        [
                            'venue_id'    => $venue->id,
                            'day_of_week' => $day,
                        ],
                        [
                            'start_time' => Carbon::createFromTime(8, 0)->format('H:i:s'),
                            'end_time'   => Carbon::createFromTime(23, 0)->format('H:i:s'),
                            'valid_from' => null,
                            'valid_to'   => null,
                            'is_active'  => true,
                        ]
                    );
                }
            }
        });
    }
}
