<?php

namespace App\Http\Controllers\Api\Venue;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Venue\StoreVenueAvailabilityRequest;
use App\Http\Requests\Venue\StoreVenueClosureRequest;
use App\Models\Booking;
use App\Models\Field;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VenueAvailabilityController extends ApiController
{
    /**
     * Replace the weekly opening hours for a venue.
     * Accepts a list of availability rows and syncs them.
     */
    public function storeAvailability(StoreVenueAvailabilityRequest $request, Venue $venue): JsonResponse
    {
        $rows = collect($request->validated()['availabilities'])
            ->map(fn($row) => [
                'day_of_week' => $row['day_of_week'],
                'start_time'  => $row['start_time'],
                'end_time'    => $row['end_time'],
                'valid_from'  => $row['valid_from'] ?? null,
                'valid_to'    => $row['valid_to'] ?? null,
                'is_active'   => $row['is_active'] ?? true,
            ]);

        $venue->availabilities()->delete();
        $venue->availabilities()->createMany($rows->all());

        return $this->jsonResponse(
            $venue->availabilities()->orderBy('day_of_week')->orderBy('start_time')->get(),
            'Venue availability saved successfully.',
            201
        );
    }

    /**
     * Add a one-off closure (exception) for a venue.
     */
    public function storeClosure(StoreVenueClosureRequest $request, Venue $venue): JsonResponse
    {
        $closure = $venue->closures()->create($request->validated());

        return $this->jsonResponse($closure, 'Venue closure saved successfully.', 201);
    }

    /**
     * Get hourly availability for a field on a given date.
     * Returns every slot within the venue's open hours, each marked
     * available or not (blocked by a closure or an existing booking).
     */
    public function fieldAvailability(Request $request, Field $field): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date    = Carbon::parse($request->date)->startOfDay();
        $weekday = strtolower($date->format('l'));

        $field->loadMissing('venue');
        $venue = $field->venue;

        // Step 1 — venue open hours for this weekday + seasonal window
        $availabilities = $venue->availabilities()
            ->active()
            ->where('day_of_week', $weekday)
            ->validOn($date->toDateString())
            ->orderBy('start_time')
            ->get();

        if ($availabilities->isEmpty()) {
            return $this->jsonResponse([
                'field_id' => $field->id,
                'date'     => $date->toDateString(),
                'weekday'  => $weekday,
                'is_open'  => false,
                'slots'    => [],
            ], 'Field availability retrieved successfully.');
        }

        // Step 2 — closures for the venue overlapping this date
        $dayStart = $date->copy()->startOfDay();
        $dayEnd   = $date->copy()->endOfDay();

        $closures = $venue->closures()
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->get();

        // Step 2b — active bookings for this field on this date
        $bookings = Booking::active()
            ->where('field_id', $field->id)
            ->where('booking_date', $date->toDateString())
            ->get(['start_time', 'end_time']);

        // Slot size from the field's active price rule, default 60 min
        $slotMinutes = (int) ($field->prices()
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->value('slot_duration') ?: 60);

        $slots = [];

        foreach ($availabilities as $window) {
            $cursor = $date->copy()->setTimeFromTimeString($window->start_time);
            $end    = $date->copy()->setTimeFromTimeString($window->end_time);

            while ($cursor->copy()->addMinutes($slotMinutes)->lessThanOrEqualTo($end)) {
                $slotStart = $cursor->copy();
                $slotEnd   = $cursor->copy()->addMinutes($slotMinutes);

                $blockedByClosure = $closures->contains(
                    fn($c) => $c->starts_at < $slotEnd && $c->ends_at > $slotStart
                );

                $slotStartStr = $slotStart->format('H:i');
                $slotEndStr   = $slotEnd->format('H:i');

                $blockedByBooking = $bookings->contains(
                    fn($b) => $b->start_time < $slotEndStr && $b->end_time > $slotStartStr
                );

                $isPast = $slotStart->isPast();

                $slots[] = [
                    'start'     => $slotStartStr,
                    'end'       => $slotEndStr,
                    'available' => ! $blockedByClosure && ! $blockedByBooking && ! $isPast,
                    'reason'    => $blockedByClosure ? 'closed'
                        : ($blockedByBooking ? 'booked'
                            : ($isPast ? 'past' : null)),
                ];

                $cursor->addMinutes($slotMinutes);
            }
        }

        return $this->jsonResponse([
            'field_id'      => $field->id,
            'date'          => $date->toDateString(),
            'weekday'       => $weekday,
            'is_open'       => true,
            'slot_minutes'  => $slotMinutes,
            'slots'         => $slots,
        ], 'Field availability retrieved successfully.');
    }
}
