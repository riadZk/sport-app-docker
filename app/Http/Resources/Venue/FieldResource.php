<?php

namespace App\Http\Resources\Venue;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $topPrice = $this->prices->first();
        $cover    = $this->getMedia('cover')->first();

        return [
            'id'           => $this->id,
            'venue_id'     => $this->venue_id,
            'name'         => $this->name,
            'surface_type' => $this->surface_type,
            'width_m'      => $this->width_m,
            'length_m'     => $this->length_m,
            'is_indoor'    => $this->is_indoor,
            'is_active'    => $this->is_active,
            'format'       => $this->format?->name,
            'price'        => $topPrice?->price,
            'currency'     => $topPrice?->currency,
            'cover_image'  => $cover ? [
                'thumb' => $cover->getUrl('thumb'),
                'card'  => $cover->getUrl('card'),
            ] : null,
        ];
    }
}
