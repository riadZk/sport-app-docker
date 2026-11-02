<?php

namespace App\Http\Controllers\Api\Field;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Field\StoreFieldRequest;
use App\Http\Requests\Field\UpdateFieldRequest;
use App\Http\Requests\Venue\StoreVenueReviewRequest;
use App\Http\Resources\Venue\FieldDetailResource;
use App\Models\Field;
use App\Models\VenueReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FieldController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $fields = Field::query()
            ->with(['venue:id,name,city', 'format:id,name,players_per_team'])
            ->when($request->venue_id, fn($q, $id) => $q->where('venue_id', $id))
            ->when($request->field_format_id, fn($q, $id) => $q->where('field_format_id', $id))
            ->when($request->surface_type, fn($q, $v) => $q->where('surface_type', $v))
            ->when($request->has('is_indoor'), fn($q) => $q->where('is_indoor', $request->boolean('is_indoor')))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->jsonResponse($fields, 'Fields retrieved successfully.');
    }

    /**
     * GET /fields/my
     * List fields belonging to venues owned by the authenticated user.
     */
    public function my(Request $request): JsonResponse
    {
        $fields = Field::query()
            ->whereHas('venue', fn($q) => $q->where('created_by', $request->user()->id))
            ->with(['venue:id,name,city', 'format:id,name,players_per_team'])
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->jsonResponse($fields, 'Fields retrieved successfully.');
    }

    public function store(StoreFieldRequest $request): JsonResponse
    {
        $field = Field::create($request->validated());

        if ($request->hasFile('cover')) {
            $field->addMedia($request->file('cover'))->toMediaCollection('cover');
        }

        if ($request->hasFile('gallery')) {
            foreach ($request->file('gallery') as $image) {
                $field->addMedia($image)->toMediaCollection('gallery');
            }
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $field->addMedia($image)->toMediaCollection('gallery');
            }
        }

        return $this->jsonResponse(
            $field->fresh(['venue:id,name,city', 'format:id,name,players_per_team']),
            'Field created successfully.',
            201
        );
    }

    public function show(Field $field): JsonResponse
    {
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

    public function update(UpdateFieldRequest $request, Field $field): JsonResponse
    {
        $field->update($request->validated());

        return $this->jsonResponse(
            $field->fresh(['venue:id,name,city', 'format:id,name,players_per_team']),
            'Field updated successfully.'
        );
    }

    public function destroy(Field $field): JsonResponse
    {
        $field->delete();

        return $this->jsonResponse(null, 'Field deleted successfully.');
    }

    /**
     * POST /fields/{field}/reviews
     * Reviews are stored against the field's venue.
     */
    public function storeReview(StoreVenueReviewRequest $request, Field $field): JsonResponse
    {
        $alreadyReviewed = VenueReview::where('venue_id', $field->venue_id)
            ->where('user_id', auth()->id())
            ->exists();

        if ($alreadyReviewed) {
            return $this->jsonResponse(null, 'You have already reviewed this venue.', 422);
        }

        $review = VenueReview::create([
            'venue_id' => $field->venue_id,
            'user_id'  => auth()->id(),
            'rating'   => $request->validated('rating'),
            'comment'  => $request->validated('comment'),
        ]);

        return $this->jsonResponse($review, 'Review submitted successfully.', 201);
    }

    /**
     * GET /fields/{field}/reviews/summary
     */
    public function reviewsSummary(Field $field): JsonResponse
    {
        return $this->jsonResponse(
            $field->venue->ratingSummary(),
            'Rating summary retrieved successfully.'
        );
    }
}
