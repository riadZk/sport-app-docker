<?php

namespace App\Http\Controllers\Api\DashboardAdmin\Booking;

use App\Http\Controllers\Api\ApiController;
use App\Models\Booking;
use Illuminate\Http\Request;

class AdminBookingController extends ApiController
{
    public function listing(Request $request)
    {
        $request->validate([
            'status'         => ['nullable', 'in:pending,confirmed,cancelled,completed'],
            'payment_status' => ['nullable', 'in:unpaid,paid,refunded'],
            'price_min'      => ['nullable', 'numeric', 'min:0'],
            'price_max'      => ['nullable', 'numeric', 'min:0'],
            'date_from'      => ['nullable', 'date'],
            'date_to'        => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort_by'        => ['nullable', 'in:latest,oldest,price_asc,price_desc'],
            'per_page'       => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $sortBy = $request->input('sort_by', 'latest');

        $bookings = Booking::query()
            ->with(['field.venue' => fn($q) => $q->withTrashed(), 'user', 'contact'])
            ->when($request->filled('status'),         fn($q) => $q->where('status', $request->status))
            ->when($request->filled('payment_status'), fn($q) => $q->where('payment_status', $request->payment_status))
            ->when($request->filled('price_min'),      fn($q) => $q->where('total_price', '>=', $request->price_min))
            ->when($request->filled('price_max'),      fn($q) => $q->where('total_price', '<=', $request->price_max))
            ->when($request->filled('date_from'),      fn($q) => $q->whereDate('booking_date', '>=', $request->date_from))
            ->when($request->filled('date_to'),        fn($q) => $q->whereDate('booking_date', '<=', $request->date_to))
            ->when($sortBy === 'latest',     fn($q) => $q->latest())
            ->when($sortBy === 'oldest',     fn($q) => $q->oldest())
            ->when($sortBy === 'price_asc',  fn($q) => $q->orderBy('total_price', 'asc'))
            ->when($sortBy === 'price_desc', fn($q) => $q->orderBy('total_price', 'desc'))
            ->paginate($request->integer('per_page', 15));

        $bookings->getCollection()->transform(fn($b) => [
            'id'             => $b->id,
            'reference'      => $b->reference,
            'status'         => $b->status,
            'payment_status' => $b->payment_status,
            'booking_date'   => $b->booking_date,
            'start_time'     => $b->start_time,
            'end_time'       => $b->end_time,
            'total_price'    => $b->total_price,
            'venue'          => $b->field && $b->field->venue ? [
                'id'   => $b->field->venue->id,
                'name' => $b->field->venue->name,
                'city' => $b->field->venue->city,
            ] : null,
            'field'          => $b->field ? [
                'id'    => $b->field->id,
                'name'  => $b->field->name,
            ] : null,
            'user'           => $b->user ? [
                'id'         => $b->user->id,
                'first_name' => $b->user->first_name,
                'last_name'  => $b->user->last_name,
                'email'      => $b->user->email,
            ] : null,
        ]);

        return $this->jsonResponse($bookings, 'Bookings retrieved successfully.');
    }

    public function detail($id)
    {
        $booking = Booking::with(['field.venue' => fn($q) => $q->withTrashed(), 'field.prices', 'user', 'contact'])->find($id);

        if (! $booking) {
            return $this->jsonResponse(null, 'Booking not found', 404);
        }

        $data = [
            'id'             => $booking->id,
            'reference'      => $booking->reference,
            'status'         => $booking->status,
            'payment_status' => $booking->payment_status,
            'booking_date'   => $booking->booking_date,
            'start_time'     => $booking->start_time,
            'end_time'       => $booking->end_time,
            'total_price'    => $booking->total_price,
            'note'           => $booking->note,
            'venue'          => $booking->field && $booking->field->venue ? [
                'id'   => $booking->field->venue->id,
                'name' => $booking->field->venue->name,
                'city' => $booking->field->venue->city,
            ] : null,
            'field'          => $booking->field ? [
                'id'     => $booking->field->id,
                'name'   => $booking->field->name,
                'prices' => $booking->field->prices,
            ] : null,
            'user'           => $booking->user ? [
                'id'         => $booking->user->id,
                'first_name' => $booking->user->first_name,
                'last_name'  => $booking->user->last_name,
                'email'      => $booking->user->email,
            ] : null,
            'contact'        => $booking->contact,
        ];

        return $this->jsonResponse($data, 'Booking retrieved successfully.');
    }

    

}
