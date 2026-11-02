<?php

namespace App\Http\Resources\Venue;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenueMapResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $minPrice = $this->fields
            ->flatMap->prices
            ->min('price');

        $image = $this->getFirstMediaUrl('cover', 'card')
            ?: $this->getFirstMediaUrl('cover')
            ?: $this->getFirstMediaUrl('gallery')
            ?: null;

        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'city'           => $this->city,
            'latitude'       => $this->latitude,
            'longitude'      => $this->longitude,
            'distance_km'    => isset($this->distance) && $this->distance !== null
                ? round((float) $this->distance, 2)
                : null,
            'average_rating' => isset($this->reviews_avg_rating) && $this->reviews_avg_rating !== null
                ? round((float) $this->reviews_avg_rating, 1)
                : null,
            'min_price'      => $this->field_prices_min_price ?? ($minPrice ? (float) $minPrice : null),
            'image'          => $image,
        ];
    }
}
