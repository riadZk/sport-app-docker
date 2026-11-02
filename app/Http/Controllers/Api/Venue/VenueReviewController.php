<?php

namespace App\Http\Controllers\Api\Venue;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Venue\StoreVenueReviewRequest;
use App\Http\Requests\Venue\UpdateVenueReviewRequest;
use App\Models\Venue;
use App\Models\VenueReview;
use Illuminate\Http\JsonResponse;

class VenueReviewController extends ApiController
{
    /**
     * GET /venues/{venue}/reviews
     */
    public function index(Venue $venue): JsonResponse
    {
        $reviews = $venue->reviews()
            ->with('user:id,first_name,last_name,username')
            ->latest()
            ->get();

        return $this->jsonResponse([
            'summary' => $venue->ratingSummary(),
            'reviews' => $reviews,
        ], 'Reviews retrieved successfully.');
    }

    /**
     * GET /venues/{venue}/reviews/summary
     */
    public function summary(Venue $venue): JsonResponse
    {
        return $this->jsonResponse(
            $venue->ratingSummary(),
            'Rating summary retrieved successfully.'
        );
    }

    /**
     * POST /venues/{venue}/reviews
     */
    public function store(StoreVenueReviewRequest $request, Venue $venue): JsonResponse
    {
        $alreadyReviewed = VenueReview::where('venue_id', $venue->id)
            ->where('user_id', auth()->id())
            ->exists();

        if ($alreadyReviewed) {
            return response()->json([
                'status'  => 422,
                'message' => 'You have already reviewed this venue.',
                'results' => null,
            ], 422);
        }

        $review = VenueReview::create([
            'venue_id' => $venue->id,
            'user_id'  => auth()->id(),
            'rating'   => $request->validated('rating'),
            'comment'  => $request->validated('comment'),
        ]);

        return $this->jsonResponse(
            $review,
            'Review submitted successfully.',
            201
        );
    }

    /**
     * GET /venues/{venue}/reviews/{review}
     */
    public function show(Venue $venue, VenueReview $review): JsonResponse
    {
        $this->abortIfNotOwnedByVenue($review, $venue);

        return $this->jsonResponse(
            $review->load('user:id,first_name,last_name,username'),
            'Review retrieved successfully.'
        );
    }

    /**
     * PUT /venues/{venue}/reviews/{review}
     */
    public function update(UpdateVenueReviewRequest $request, Venue $venue, VenueReview $review): JsonResponse
    {
        $this->abortIfNotOwnedByVenue($review, $venue);
        $this->abortIfNotOwner($review);

        $review->update($request->validated());

        return $this->jsonResponse(
            $review,
            'Review updated successfully.'
        );
    }

    /**
     * DELETE /venues/{venue}/reviews/{review}
     */
    public function destroy(Venue $venue, VenueReview $review): JsonResponse
    {
        $this->abortIfNotOwnedByVenue($review, $venue);
        $this->abortIfNotOwner($review);

        $review->delete();

        return $this->jsonResponse(null, 'Review deleted successfully.');
    }

    private function abortIfNotOwnedByVenue(VenueReview $review, Venue $venue): void
    {
        if ($review->venue_id !== $venue->id) {
            abort(404, 'Review not found for this venue.');
        }
    }

    private function abortIfNotOwner(VenueReview $review): void
    {
        if ($review->user_id !== auth()->id()) {
            abort(403, 'You are not allowed to modify this review.');
        }
    }
}
