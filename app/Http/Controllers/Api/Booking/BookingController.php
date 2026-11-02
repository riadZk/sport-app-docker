<?php

namespace App\Http\Controllers\Api\Booking;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BookingController extends ApiController
{
    /**
     * GET /bookings
     * List the authenticated user's bookings.
     */
    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::query()
            ->where('user_id', auth()->id())
            ->with(['field.venue', 'contact'])
            ->when(
                $request->filled('status'),
                fn($q) => $q->where('status', $request->status)
            )
            ->when(
                $request->filled('date'),
                fn($q) => $q->where('booking_date', $request->date)
            )
            ->latest('booking_date')
            ->paginate($request->integer('per_page', 15));

        return $this->jsonResponse($bookings, 'Bookings retrieved successfully.');
    }

    /**
     * GET /bookings/{booking}
     */
    public function show(Booking $booking): JsonResponse
    {
        if ($booking->user_id !== auth()->id()) {
            return $this->jsonResponse(null, 'Booking not found.', 404);
        }

        return $this->jsonResponse(
            $booking->load(['field.venue', 'contact']),
            'Booking retrieved successfully.'
        );
    }

    /**
     * POST /bookings
     * Create a new booking and check for time-slot conflicts first.
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $data = $request->validated();

        // ── Conflict check ────────────────────────────────────────────────
        $conflict = Booking::active()
            ->overlapping(
                $data['field_id'],
                $data['booking_date'],
                $data['start_time'],
                $data['end_time']
            )
            ->first();

        if ($conflict) {
            return response()->json([
                'status'  => 422,
                'message' => 'This time slot is already booked. Please choose a different time.',
                'results' => [
                    'conflicting_booking' => [
                        'booking_date' => $conflict->booking_date->toDateString(),
                        'start_time'   => $conflict->start_time,
                        'end_time'     => $conflict->end_time,
                        'status'       => $conflict->status,
                    ],
                ],
            ], 422);
        }

        // ── Create booking ────────────────────────────────────────────────
        $booking = Booking::create([
            'reference'      => strtoupper(Str::random(10)),
            'field_id'       => $data['field_id'],
            'user_id'        => auth()->id(),
            'booking_date'   => $data['booking_date'],
            'start_time'     => $data['start_time'],
            'end_time'       => $data['end_time'],
            'total_price'    => $data['total_price'],
            'status'         => 'pending',
            'payment_status' => 'unpaid',
            'note'           => $data['note'] ?? null,
        ]);

        // ── Create contact ────────────────────────────────────────────────
        $booking->contact()->create($data['contact']);

        return $this->jsonResponse(
            $booking->load(['field.venue', 'contact']),
            'Booking created successfully.',
            201
        );
    }

    /**
     * DELETE /bookings/{booking}
     * Cancel a booking (only if pending or confirmed).
     */
    public function cancel(Booking $booking): JsonResponse
    {
        if ($booking->user_id !== auth()->id()) {
            return $this->jsonResponse(null, 'Booking not found.', 404);
        }

        if (! in_array($booking->status, ['pending', 'confirmed'])) {
            return response()->json([
                'status'  => 422,
                'message' => 'Only pending or confirmed bookings can be cancelled.',
                'results' => null,
            ], 422);
        }

        $booking->update([
            'status' => 'cancelled',
        ]);

        return $this->jsonResponse($booking, 'Booking cancelled successfully.');
    }
}
