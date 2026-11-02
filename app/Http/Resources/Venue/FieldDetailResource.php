<?php

namespace App\Http\Resources\Venue;

use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $topPrice = $this->prices->first();
        // return $this->prices->sortByDesc('priority')->first() ?? null;

        $nearbyVenues = $this->resolveNearbyVenues();

        return [
            'id'           => $this->id,
            'venue_id'     => $this->venue_id,
            'venue'        => $this->venue ? [
                'id'          => $this->venue->id,
                'name'        => $this->venue->name,
                'city'        => $this->venue->city,
                'description' => $this->venue->description,
                'created_by'  => $this->venue->creator ? [
                    'id'   => $this->venue->creator->id,
                    'name' => trim($this->venue->creator->first_name . ' ' . $this->venue->creator->last_name)
                ] : null,
            ] : null,
            'name'         => $this->name,
            'format'       => $this->format?->name,
            'price'        => $topPrice?->price,
            'currency'     => $topPrice?->currency,
            'images'       => $this->getMedia('*')->map(fn($media) => [
                'collection' => $media->collection_name,
                'thumb'      => $media->getUrl('thumb'),
                'card'       => $media->getUrl('card'),
                'original'   => $media->getUrl(),
            ])->values(),
            'amenities'    => $this->venue
                ? $this->venue->amenities->map(fn($amenity) => [
                    'id'   => $amenity->id,
                    'name' => $amenity->name,
                    'icon' => $amenity->icon,
                ])->values()
                : [],
            'rating'       => $this->venue?->averageRating(),
            'reviews'      => $this->venue
                ? $this->venue->reviews->map(fn($review) => [
                    'id'         => $review->id,
                    'rating'     => $review->rating,
                    'comment'    => $review->comment,
                    'created_at' => $review->created_at?->toISOString(),
                    'user'       => $review->user ? [
                        'id'          => $review->user->id,
                        'name'        => trim($review->user->first_name . ' ' . $review->user->last_name),
                        'username'    => $review->user->username,
                        'picture_url' => $review->user->picture_url,
                    ] : null,
                ])->values()
                : [],
            'nearby_venues' => $nearbyVenues,
        ];
    }

    private function resolveNearbyVenues(): array
    {
        $venue = $this->venue;

        if (! $venue || $venue->latitude === null || $venue->longitude === null) {
            return [];
        }

        $lat = (float) $venue->latitude;
        $lng = (float) $venue->longitude;

        return Venue::query()
            ->where('is_active', true)
            ->where('id', '!=', $venue->id)
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
            ->get()
            ->map(fn($v) => [
                'id'             => $v->id,
                'name'           => $v->name,
                'city'           => $v->city,
                'distance_km'    => $v->distance !== null ? round((float) $v->distance, 2) : null,
                'average_rating' => $v->reviews_avg_rating !== null
                    ? round((float) $v->reviews_avg_rating, 1)
                    : null,
                'price'          => $v->field_prices_min_price,
            ])
            ->values()
            ->all();
    }
}
