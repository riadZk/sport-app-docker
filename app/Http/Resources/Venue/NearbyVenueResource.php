<?php

namespace App\Http\Resources\Venue;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NearbyVenueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'city'           => $this->city,
            'distance_km'    => $this->distance !== null
                ? round((float) $this->distance, 2)
                : null,
            'average_rating' => $this->reviews_avg_rating !== null
                ? round((float) $this->reviews_avg_rating, 1)
                : null,
            'price'          => $this->field_prices_min_price,
        ];
    }
}
