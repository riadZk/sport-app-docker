<?php

namespace App\Http\Resources\Venue;

use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'cover_url'    => $this->getFirstMediaUrl('cover'),
            'gallery_urls' => $this->getMedia('gallery')->map->getUrl()->values(),
            'description' => $this->description,
            'address'     => $this->address,
            'city'        => $this->city,
            'phone'       => $this->phone,
            'latitude'    => $this->latitude,
            'longitude'   => $this->longitude,
            'is_active'   => $this->is_active,
            'creator'     => $this->creator ? [
                'id'   => $this->creator->id,
                'name' => trim($this->creator->first_name . ' ' . $this->creator->last_name),
                'picture_url' => $this->creator->picture_url,
            ] : null,
            'amenities'   => $this->amenities->map(fn($amenity) => [
                'id'   => $amenity->id,
                'name' => $amenity->name,
                'icon' => $amenity->icon,
            ])->values(),
            'min_price'   => $this->field_prices_min_price,
            'rating'      => $this->averageVenueRating(),
            'reviews'     => $this->reviews->map(fn($review) => [
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
            ])->values(),
            'nearby_venues' => $this->resolveNearbyVenues(),

            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }

    private function averageVenueRating(): ?float
    {
        $ratings = $this->reviews->pluck('rating');

        if ($ratings->isEmpty()) {
            return null;
        }

        return round($ratings->avg(), 1);
    }

    private function resolveNearbyVenues(): array
    {
        if ($this->latitude === null || $this->longitude === null) {
            return [];
        }

        $lat = (float) $this->latitude;
        $lng = (float) $this->longitude;

        return Venue::query()
            ->where('is_active', true)
            ->where('id', '!=', $this->id)
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
            ->with(['media' => fn($q) => $q->whereIn('collection_name', ['cover', 'gallery'])])
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
                'image'          => $v->getFirstMediaUrl('cover', 'card')
                    ?: $v->getFirstMediaUrl('cover')
                    ?: $v->getFirstMediaUrl('gallery')
                    ?: null,
            ])
            ->values()
            ->all();
    }
}
