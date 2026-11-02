<?php

namespace App\Http\Controllers\Api\DashboardAdmin\Venue;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\DashboardAdmin\AdminCreateVenueRequest;
use App\Models\Field;
use App\Models\FieldFormat;
use App\Models\FieldPrice;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminVenueController extends ApiController
{
    /**
     * Create an owner user, a venue, fields, amenities, media, and availability
     * in a single atomic request covering all 7 setup steps.
     */
    public function store(AdminCreateVenueRequest $request)
    {
        $result = DB::transaction(function () use ($request) {

            // ── Step 1: Create owner ──────────────────────────────────────────
            $ownerData = $request->input('owner');

            $owner = User::create([
                'first_name'   => $ownerData['first_name'],
                'last_name'    => $ownerData['last_name'],
                'phone_number' => $ownerData['phone'],
                'email'        => $ownerData['email'],
                'password'     => Hash::make($ownerData['password']),
            ]);

            // ── Step 2 + 4: Create venue (basic info + location) ──────────────
            $venueData  = $request->input('venue', []);
            $locationData = $request->input('location', []);

            $venue = Venue::create([
                'name'        => $venueData['name'],
                'description' => $venueData['description'] ?? null,
                'phone'       => $venueData['contact_phone'] ?? null,
                'email'       => $venueData['contact_email'] ?? null,
                'latitude'    => $locationData['latitude'] ?? null,
                'longitude'   => $locationData['longitude'] ?? null,
                'country'     => $locationData['country'] ?? null,
                'city'        => $locationData['city'] ?? null,
                'address'     => $locationData['address'] ?? null,
                'is_active'   => true,
                'created_by'  => $owner->id,
            ]);

            // ── Step 3: Media (logo, cover, gallery) ──────────────────────────
            if ($request->hasFile('logo')) {
                $venue->addMediaFromRequest('logo')
                    ->usingFileName('venue_logo_' . $venue->id . '_' . now()->timestamp . '.' . $request->file('logo')->getClientOriginalExtension())
                    ->toMediaCollection('logo');
            }

            if ($request->hasFile('cover')) {
                $venue->addMediaFromRequest('cover')
                    ->usingFileName('venue_cover_' . $venue->id . '_' . now()->timestamp . '.' . $request->file('cover')->getClientOriginalExtension())
                    ->toMediaCollection('cover');
            }

            if ($request->hasFile('gallery')) {
                foreach ($request->file('gallery') as $index => $image) {
                    $venue->addMedia($image)
                        ->usingFileName('venue_gallery_' . $venue->id . '_' . now()->timestamp . '_' . $index . '.' . $image->getClientOriginalExtension())
                        ->toMediaCollection('gallery');
                }
            }

            // ── Step 5: Amenities ─────────────────────────────────────────────
            if ($request->filled('amenity_ids')) {
                $venue->amenities()->sync($request->input('amenity_ids'));
            }

            // ── Step 6: Fields + prices ───────────────────────────────────────
            $createdFields = [];

            foreach ($request->input('fields', []) as $fieldConfig) {
                $format = FieldFormat::find($fieldConfig['field_format_id']);
                $count  = (int) $fieldConfig['count'];

                for ($i = 1; $i <= $count; $i++) {
                    $field = Field::create([
                        'venue_id'        => $venue->id,
                        'field_format_id' => $fieldConfig['field_format_id'],
                        'name'            => ($format->name ?? 'Field') . ' #' . $i,
                        'surface_type'    => $fieldConfig['surface_type'],
                        'is_active'       => true,
                    ]);

                    FieldPrice::create([
                        'field_id'     => $field->id,
                        'label'        => 'Standard',
                        'pricing_type' => 'hourly',
                        'price'        => $fieldConfig['price_per_hour'],
                        'currency'     => 'MAD',
                        'is_active'    => true,
                    ]);

                    $createdFields[] = $field->id;
                }
            }

            // ── Step 7: Availability ──────────────────────────────────────────
            foreach ($request->input('availabilities', []) as $slot) {
                VenueAvailability::create([
                    'venue_id'    => $venue->id,
                    'day_of_week' => $slot['day_of_week'],
                    'start_time'  => $slot['start_time'],
                    'end_time'    => $slot['end_time'],
                    'is_active'   => $slot['is_active'] ?? true,
                ]);
            }

            return [
                'owner' => [
                    'id'         => $owner->id,
                    'first_name' => $owner->first_name,
                    'last_name'  => $owner->last_name,
                    'email'      => $owner->email,
                ],
                'venue'          => $venue->load(['amenities', 'fields.prices', 'availabilities']),
                'fields_created' => count($createdFields),
            ];
        });

        return $this->jsonResponse($result, 'Venue created successfully', 201);
    }

    public function listing(Request $request)
    {
        $request->validate([
            'amenities'   => ['nullable', 'array'],
            'amenities.*' => ['integer'],
            'sort_by'     => ['nullable', 'in:price_asc,price_desc,rating,latest'],
            'status'      => ['nullable', 'in:active,inactive'],
        ]);

        $sortBy = $request->input('sort_by', 'latest');

        $venues = Venue::query()
            ->when($request->filled('city'),   fn($q) => $q->where('city', $request->city))
            ->when($request->filled('name'),   fn($q) => $q->where('name', 'like', '%' . $request->name . '%'))
            ->when($request->filled('status'), fn($q) => $q->where('is_active', $request->status === 'active'))
            ->when($request->filled('amenities'), fn($q) => $q->whereHas(
                'amenities',
                fn($q) => $q->whereIn('amenities.id', $request->amenities)
            ))
            ->withAvg('reviews', 'rating')
            ->withMin('fieldPrices', 'price')
            ->with(['media' => fn($q) => $q->whereIn('collection_name', ['cover', 'gallery'])])
            ->when($sortBy === 'price_asc',  fn($q) => $q->orderByRaw('ISNULL(field_prices_min_price) ASC, field_prices_min_price ASC'))
            ->when($sortBy === 'price_desc', fn($q) => $q->orderByRaw('ISNULL(field_prices_min_price) ASC, field_prices_min_price DESC'))
            ->when($sortBy === 'rating',     fn($q) => $q->orderByRaw('ISNULL(reviews_avg_rating) ASC, reviews_avg_rating DESC'))
            ->when($sortBy === 'latest',     fn($q) => $q->latest())
            ->paginate($request->integer('per_page', 15));

        $venues->getCollection()->transform(fn($v) => [
            'id'             => $v->id,
            'name'           => $v->name,
            'city'           => $v->city,
            'is_active'      => $v->is_active,
            'longitude'       => $v->longitude,
            'latitude'        => $v->latitude,
            'average_rating' => $v->reviews_avg_rating !== null
                ? round((float) $v->reviews_avg_rating, 1)
                : null,
            'min_price'      => $v->field_prices_min_price,
            'image'          => $v->getFirstMediaUrl('cover', 'card')
                ?: $v->getFirstMediaUrl('cover')
                ?: $v->getFirstMediaUrl('gallery')
                ?: null,
        ]);

        return $this->jsonResponse($venues, 'Venues retrieved successfully.');
    }

    public function detailVenue($id)
    {
        $venue = Venue::with([
            'creator',
            'amenities',
            'fields.prices',
            'availabilities',
            'closures',
            'reviews',
            'media',
        ])->find($id);

        if (! $venue) {
            return $this->jsonResponse(null, 'Venue not found', 404);
        }

        $allMedia = $venue->media->toBase(); // plain Collection, bypasses Spatie's custom jsonSerialize

        $venue->unsetRelation('media');

        return $this->jsonResponse([
            'venue'          => $venue,
            'media'          => [
                'logo'    => $allMedia->where('collection_name', 'logo')->map(fn($m) => $m->getUrl())->values()->first(),
                'cover'   => $allMedia->where('collection_name', 'cover')->map(fn($m) => $m->getUrl())->values()->first(),
                'gallery' => $allMedia->where('collection_name', 'gallery')->map(fn($m) => $m->getUrl())->values(),
            ],
            'average_rating'   => $venue->averageRating(),
            'review_count'     => $venue->reviewCount(),
            'rating_breakdown' => $venue->ratingBreakdown(),
        ], 'Venue retrieved successfully');
    }

    public function updateVenue($id, Request $request)
    {
        $venue = Venue::find($id);

        if (! $venue) {
            return $this->jsonResponse(null, 'Venue not found', 404);
        }

        $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'address'     => ['sometimes', 'nullable', 'string'],
            'city'        => ['sometimes', 'nullable', 'string'],
            'country'     => ['sometimes', 'nullable', 'string'],
            'phone'       => ['sometimes', 'nullable', 'string'],
            'email'       => ['sometimes', 'nullable', 'email'],
            'latitude'    => ['sometimes', 'nullable', 'numeric'],
            'longitude'   => ['sometimes', 'nullable', 'numeric'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        $venue->update($request->only([
            'name', 'description', 'address', 'city', 'country',
            'phone', 'email', 'latitude', 'longitude', 'is_active',
        ]));

        return $this->jsonResponse($venue->fresh(), 'Venue updated successfully');
    }

    public function updateLogo($id, Request $request)
    {
        $venue = Venue::find($id);

        if (! $venue) {
            return $this->jsonResponse(null, 'Venue not found', 404);
        }

        $request->validate([
            'logo' => ['required', 'image', 'max:5120'],
        ]);

        $venue->clearMediaCollection('logo');

        $venue->addMediaFromRequest('logo')
            ->usingFileName('venue_logo_' . $venue->id . '_' . now()->timestamp . '.' . $request->file('logo')->getClientOriginalExtension())
            ->toMediaCollection('logo');

        return $this->jsonResponse(
            ['url' => $venue->getFirstMediaUrl('logo')],
            'Logo updated successfully'
        );
    }

    public function updateCover($id, Request $request)
    {
        $venue = Venue::find($id);

        if (! $venue) {
            return $this->jsonResponse(null, 'Venue not found', 404);
        }

        $request->validate([
            'cover' => ['required', 'image', 'max:10240'],
        ]);

        $venue->clearMediaCollection('cover');

        $venue->addMediaFromRequest('cover')
            ->usingFileName('venue_cover_' . $venue->id . '_' . now()->timestamp . '.' . $request->file('cover')->getClientOriginalExtension())
            ->toMediaCollection('cover');

        return $this->jsonResponse(
            ['url' => $venue->getFirstMediaUrl('cover')],
            'Cover updated successfully'
        );
    }

    public function updateGallery($id, Request $request)
    {
        $venue = Venue::find($id);

        if (! $venue) {
            return $this->jsonResponse(null, 'Venue not found', 404);
        }

        $request->validate([
            'images'   => ['required', 'array', 'min:1'],
            'images.*' => ['image', 'max:10240'],
            'replace'  => ['sometimes', 'boolean'],
        ]);

        if ($request->boolean('replace', false)) {
            $venue->clearMediaCollection('gallery');
        }

        $urls = [];
        foreach ($request->file('images') as $index => $image) {
            $media = $venue->addMedia($image)
                ->usingFileName('venue_gallery_' . $venue->id . '_' . now()->timestamp . '_' . $index . '.' . $image->getClientOriginalExtension())
                ->toMediaCollection('gallery');

            $urls[] = $media->getUrl();
        }

        return $this->jsonResponse(
            ['urls' => $urls],
            'Gallery updated successfully'
        );
    }

    public function deleteVenue($id)
    {
        $venue = Venue::find($id);

        if (! $venue) {
            return $this->jsonResponse(null, 'Venue not found', 404);
        }

        $venue->delete();

        return $this->jsonResponse(null, 'Venue deleted successfully');
    }

    public function updateAmenities($id, Request $request)
    {
        $venue = Venue::find($id);

        if (! $venue) {
            return $this->jsonResponse(null, 'Venue not found', 404);
        }

        $request->validate([
            'amenity_ids'   => ['present', 'array'],
            'amenity_ids.*' => ['integer', 'exists:amenities,id'],
        ]);

        $venue->amenities()->sync($request->input('amenity_ids'));

        return $this->jsonResponse(
            $venue->load('amenities'),
            'Amenities updated successfully'
        );
    }

    public function getFieldsByFormat($id, Request $request)
    {
        $venue = Venue::find($id);

        if (! $venue) {
            return $this->jsonResponse(null, 'Venue not found', 404);
        }

        $groups = $venue->fields()
            ->with(['format:id,name,players_per_team', 'prices'])
            ->get()
            ->groupBy('field_format_id')
            ->map(fn($fields) => [
                'field_format_id'  => $fields->first()->field_format_id,
                'format'           => $fields->first()->format?->name,
                'players_per_team' => $fields->first()->format?->players_per_team,
                'count'            => $fields->count(),
                'fields'           => $fields->values(),
            ])
            ->values();

        return $this->jsonResponse($groups, 'Fields retrieved successfully');
    }

    public function updateFields($id, Request $request)
    {
        $venue = Venue::find($id);

        if (! $venue) {
            return $this->jsonResponse(null, 'Venue not found', 404);
        }

        $request->validate([
            'fields'                   => ['required', 'array', 'min:1'],
            'fields.*.field_format_id' => ['required', 'integer', 'exists:field_formats,id'],
            'fields.*.surface_type'    => ['required', 'in:grass,artificial,futsal'],
            'fields.*.price_per_hour'  => ['required', 'numeric', 'min:0'],
            'fields.*.quantity'        => ['required', 'integer', 'min:0'],
            'fields.*.is_active'       => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $venue) {
            foreach ($request->input('fields', []) as $config) {
                $format   = FieldFormat::find($config['field_format_id']);
                $quantity = (int) $config['quantity'];
                $isActive = $config['is_active'] ?? true;

                $existing = $venue->fields()
                    ->where('field_format_id', $config['field_format_id'])
                    ->orderBy('id')
                    ->get();

                // Update the fields we keep, and create any missing ones.
                for ($i = 0; $i < $quantity; $i++) {
                    $field = $existing->get($i);

                    if ($field) {
                        $field->update([
                            'surface_type' => $config['surface_type'],
                            'is_active'    => $isActive,
                        ]);
                    } else {
                        $field = Field::create([
                            'venue_id'        => $venue->id,
                            'field_format_id' => $config['field_format_id'],
                            'name'            => ($format->name ?? 'Field') . ' #' . ($i + 1),
                            'surface_type'    => $config['surface_type'],
                            'is_active'       => $isActive,
                        ]);
                    }

                    $price = $field->prices()->orderBy('id')->first();

                    if ($price) {
                        $price->update(['price' => $config['price_per_hour']]);
                    } else {
                        FieldPrice::create([
                            'field_id'     => $field->id,
                            'label'        => 'Standard',
                            'pricing_type' => 'hourly',
                            'price'        => $config['price_per_hour'],
                            'currency'     => 'MAD',
                            'is_active'    => true,
                        ]);
                    }
                }

                // Remove extra fields beyond the requested quantity.
                $existing->slice($quantity)->each(fn($field) => $field->delete());
            }
        });

        return $this->jsonResponse(
            $venue->load(['fields.format:id,name,players_per_team', 'fields.prices']),
            'Fields updated successfully'
        );
    }
}
