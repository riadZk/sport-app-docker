<?php

namespace App\Http\Requests\DashboardAdmin;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rules\Password;

class AdminCreateVenueRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ── Step 1: Owner ─────────────────────────────────────────────────
            'owner.first_name'  => ['required', 'string', 'max:100'],
            'owner.last_name'   => ['required', 'string', 'max:100'],
            'owner.phone'       => ['required', 'string', 'max:20'],
            'owner.email'       => ['required', 'email', 'max:150', 'unique:users,email'],
            'owner.password'    => ['required', Password::min(8)],

            // ── Step 2: Venue basic info ──────────────────────────────────────
            'venue.name'            => ['required', 'string', 'max:100'],
            'venue.description'     => ['nullable', 'string'],
            'venue.contact_phone'   => ['nullable', 'string', 'max:20'],
            'venue.contact_email'   => ['nullable', 'email', 'max:150'],

            // ── Step 3: Media ─────────────────────────────────────────────────
            'logo'          => ['nullable', 'image', 'max:5120'],
            'cover'         => ['nullable', 'image', 'max:10240'],
            'gallery'       => ['nullable', 'array'],
            'gallery.*'     => ['image', 'max:5120'],

            // ── Step 4: Location ──────────────────────────────────────────────
            'location.latitude'   => ['nullable', 'numeric', 'between:-90,90'],
            'location.longitude'  => ['nullable', 'numeric', 'between:-180,180'],
            'location.country'    => ['nullable', 'string', 'max:100'],
            'location.city'       => ['nullable', 'string', 'max:100'],
            'location.address'    => ['nullable', 'string', 'max:255'],

            // ── Step 5: Amenities ─────────────────────────────────────────────
            'amenity_ids'   => ['nullable', 'array'],
            'amenity_ids.*' => ['integer', 'exists:amenities,id'],

            // ── Step 6: Fields (field generator) ─────────────────────────────
            'fields'                    => ['nullable', 'array'],
            'fields.*.field_format_id'  => ['required_with:fields', 'integer', 'exists:field_formats,id'],
            'fields.*.count'            => ['required_with:fields', 'integer', 'min:1', 'max:50'],
            'fields.*.price_per_hour'   => ['required_with:fields', 'numeric', 'min:0'],
            'fields.*.surface_type'     => ['required_with:fields', 'string', 'in:grass,artificial,futsal'],

            // ── Step 7: Availability ──────────────────────────────────────────
            'availabilities'                => ['nullable', 'array'],
            'availabilities.*.day_of_week'  => [
                'required_with:availabilities',
                'string',
                'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'
            ],
            'availabilities.*.start_time'   => ['required_with:availabilities', 'date_format:H:i'],
            'availabilities.*.end_time'     => ['required_with:availabilities', 'date_format:H:i'],
            'availabilities.*.is_active'    => ['sometimes', 'boolean'],
        ];
    }
}
