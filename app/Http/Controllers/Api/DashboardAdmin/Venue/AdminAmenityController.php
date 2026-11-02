<?php

namespace App\Http\Controllers\Api\DashboardAdmin\Venue;

use App\Http\Controllers\Api\ApiController;
use App\Models\Amenity;
use App\Models\FieldFormat;

class AdminAmenityController extends ApiController
{
    public function getAmenities()
    {
        $amenities = Amenity::orderBy('name')->get();

        return $this->jsonResponse($amenities, 'Amenities retrieved successfully');
    }

    public function getFieldFormats()
    {
        $formats = FieldFormat::orderBy('players_per_team')->get(['id', 'name', 'players_per_team', 'description']);

        return $this->jsonResponse($formats, 'Field formats retrieved successfully.');
    }
}
