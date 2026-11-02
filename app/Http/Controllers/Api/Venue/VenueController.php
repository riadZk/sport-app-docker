<?php

namespace App\Http\Controllers\Api\Venue;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Venue\StoreVenueRequest;
use App\Http\Requests\Venue\UpdateVenueRequest;
use App\Http\Resources\Venue\FieldDetailResource;
use App\Http\Resources\Venue\FieldResource;
use App\Http\Resources\Venue\NearbyVenueResource;
use App\Http\Resources\Venue\VenueMapResource;
use App\Http\Resources\Venue\VenueResource;
use App\Models\Field;
use App\Models\FieldFormat;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VenueController extends ApiController
{
    public function nearby(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $lat = (float) $request->lat;
        $lng = (float) $request->lng;

        $venues = Venue::query()
            ->where('is_active', true)
            ->selectRaw(
                "id, name, city,
                (6371 * acos(
                    cos(radians(?)) * cos(radians(latitude))
                    * cos(radians(longitude) - radians(?))
                    + sin(radians(?)) * sin(radians(latitude))
                )) AS distance",
                [$lat, $lng, $lat]
            )
            ->withAvg('reviews', 'rating')
            ->withMin('fieldPrices', 'price')
            ->orderBy('distance')
            ->limit(4)
            ->get();

        return $this->jsonResponse(
            NearbyVenueResource::collection($venues),
            'Nearby venues retrieved successfully.'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $venues = Venue::query()
            ->when($request->city, fn($q, $city) => $q->where('city', $city))
            ->when($request->boolean('active'), fn($q) => $q->where('is_active', true))
            ->withCount('fields')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->jsonResponse($venues, 'Venues retrieved successfully.');
    }

    public function listing(Request $request): JsonResponse
    {
        $request->validate([
            'lat'        => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng'        => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'amenities'  => ['nullable', 'array'],
            'amenities.*' => ['integer'],
            'sort_by'    => ['nullable', 'in:price_asc,price_desc,rating,distance'],
        ]);

        $lat    = $request->filled('lat') ? (float) $request->lat : null;
        $lng    = $request->filled('lng') ? (float) $request->lng : null;
        $sortBy = $request->input('sort_by', 'rating');

        $haversine = $lat !== null
            ? "(6371 * acos(
                cos(radians({$lat})) * cos(radians(latitude))
                * cos(radians(longitude) - radians({$lng}))
                + sin(radians({$lat})) * sin(radians(latitude))
              ))"
            : 'NULL';

        $venues = Venue::query()
            ->where('is_active', true)
            ->when($request->filled('city'), fn($q) => $q->where('city', $request->city))
            ->when($request->filled('name'), fn($q) => $q->where('name', 'like', '%' . $request->name . '%'))
            ->when($request->filled('amenities'), fn($q) => $q->whereHas(
                'amenities',
                fn($q) =>
                $q->whereIn('amenities.id', $request->amenities)
            ))
            ->selectRaw("venues.*, {$haversine} AS distance")
            ->withAvg('reviews', 'rating')
            ->withMin('fieldPrices', 'price')
            ->with(['media' => fn($q) => $q->whereIn('collection_name', ['cover', 'gallery'])])
            ->when($sortBy === 'price_asc',  fn($q) => $q->orderByRaw('ISNULL(field_prices_min_price) ASC, field_prices_min_price ASC'))
            ->when($sortBy === 'price_desc', fn($q) => $q->orderByRaw('ISNULL(field_prices_min_price) ASC, field_prices_min_price DESC'))
            ->when($sortBy === 'rating',     fn($q) => $q->orderByRaw('ISNULL(reviews_avg_rating) ASC, reviews_avg_rating DESC'))
            ->when($sortBy === 'distance' && $lat !== null, fn($q) => $q->orderBy('distance'))
            ->when($sortBy === 'distance' && $lat === null, fn($q) => $q->latest())
            ->when(! in_array($sortBy, ['price_asc', 'price_desc', 'rating', 'distance']), fn($q) => $q->latest())
            ->paginate(10);

        $venues->getCollection()->transform(fn($v) => [
            'id'             => $v->id,
            'name'           => $v->name,
            'city'           => $v->city,
            'distance_km'    => $v->distance !== null ? round((float) $v->distance, 2) : null,
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

    public function map(Request $request): JsonResponse
    {
        $request->validate([
            'lat'         => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng'         => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'amenities'   => ['nullable', 'array'],
            'amenities.*' => ['integer'],
            'sort_by'     => ['nullable', 'in:price_asc,price_desc,rating,distance'],
        ]);

        $lat    = $request->filled('lat') ? (float) $request->lat : null;
        $lng    = $request->filled('lng') ? (float) $request->lng : null;
        $sortBy = $request->input('sort_by');

        $haversine = $lat !== null
            ? "(6371 * acos(
                cos(radians({$lat})) * cos(radians(latitude))
                * cos(radians(longitude) - radians({$lng}))
                + sin(radians({$lat})) * sin(radians(latitude))
              ))"
            : 'NULL';

        $venues = Venue::query()
            ->where('is_active', true)
            ->when($request->filled('city'), fn($q) => $q->where('city', $request->city))
            ->when($request->filled('name'), fn($q) => $q->where('name', 'like', '%' . $request->name . '%'))
            ->when($request->filled('amenities'), fn($q) => $q->whereHas(
                'amenities',
                fn($q) => $q->whereIn('amenities.id', $request->amenities)
            ))
            ->selectRaw("venues.id, venues.name, venues.city, venues.latitude, venues.longitude, {$haversine} AS distance")
            ->withAvg('reviews', 'rating')
            ->withMin('fieldPrices', 'price')
            ->with([
                'fields' => fn($q) => $q
                    ->where('is_active', true)
                    ->with([
                        'prices' => fn($q) => $q
                            ->where('is_active', true)
                            ->orderBy('priority', 'desc')
                            ->limit(1),
                    ]),
                'media' => fn($q) => $q
                    ->whereIn('collection_name', ['cover', 'gallery']),
            ])
            ->when($sortBy === 'price_asc',  fn($q) => $q->orderByRaw('ISNULL(field_prices_min_price) ASC, field_prices_min_price ASC'))
            ->when($sortBy === 'price_desc', fn($q) => $q->orderByRaw('ISNULL(field_prices_min_price) ASC, field_prices_min_price DESC'))
            ->when($sortBy === 'rating',     fn($q) => $q->orderByRaw('ISNULL(reviews_avg_rating) ASC, reviews_avg_rating DESC'))
            ->when($sortBy === 'distance' && $lat !== null, fn($q) => $q->orderBy('distance'))
            ->get();

        return $this->jsonResponse(
            VenueMapResource::collection($venues),
            'Venue map data retrieved successfully.'
        );
    }

    public function fieldsByFormat(Venue $venue): JsonResponse
    {
        $formats = FieldFormat::query()
            ->with(['fields' => fn($q) => $q
                ->where('is_active', true)
                ->where('venue_id', $venue->id)
                ->select('id', 'name', 'field_format_id')
                ->withMin('prices', 'price')
                ->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->filter(fn($format) => $format->fields->isNotEmpty())
            ->map(fn($format) => [
                'id'     => $format->id,
                'name'   => $format->name,
                'fields' => $format->fields->map(fn($field) => [
                    'id'        => $field->id,
                    'name'      => $field->name,
                    'min_price' => $field->prices_min_price,
                ])->values(),
            ])
            ->values();

        return $this->jsonResponse($formats, 'Fields grouped by format retrieved successfully.');
    }

    public function store(StoreVenueRequest $request): JsonResponse
    {
        $venue = Venue::create($request->validated());

        return $this->jsonResponse($venue, 'Venue created successfully.', 201);
    }

    public function show(Venue $venue): JsonResponse
    {
        $venue->loadMissing([
            'creator:id,first_name,last_name',
            'amenities',
            'reviews' => fn($q) => $q->with('user:id,first_name,last_name,username')->latest(),
        ]);

        $venue->loadMin('fieldPrices', 'price');

        return $this->jsonResponse(new VenueResource($venue), 'Venue retrieved successfully.');
    }

    public function showField(Venue $venue, Field $field): JsonResponse
    {
        abort_if($field->venue_id !== $venue->id, 404, 'Field not found for this venue.');

        $field->load([
            'venue:id,name,city,description,created_by,latitude,longitude',
            'venue.creator:id,first_name,last_name',
            'venue.amenities',
            'venue.reviews' => fn($q) => $q->with('user:id,first_name,last_name,username')->latest(),
            'format',
            'prices' => fn($q) => $q
                ->where('is_active', true)
                ->orderBy('priority', 'desc')
                ->limit(1),
            'media',
        ]);

        return $this->jsonResponse(new FieldDetailResource($field), 'Field retrieved successfully.');
    }

    public function update(UpdateVenueRequest $request, Venue $venue): JsonResponse
    {
        $venue->update($request->validated());

        return $this->jsonResponse($venue->fresh(), 'Venue updated successfully.');
    }

    public function destroy(Venue $venue): JsonResponse
    {
        $venue->delete();

        return $this->jsonResponse(null, 'Venue deleted successfully.');
    }

    /**
     * DELETE /venues/{venue}/gallery/{media}
     * Remove a single image from the venue's gallery collection.
     */
    public function deleteGalleryImage(Venue $venue, int $media): JsonResponse
    {
        $mediaItem = $venue->getMedia('gallery')->firstWhere('id', $media);

        if (! $mediaItem) {
            return $this->jsonResponse(null, 'Gallery image not found for this venue.', 404);
        }

        $mediaItem->delete();

        return $this->jsonResponse(null, 'Gallery image deleted successfully.');
    }
}
