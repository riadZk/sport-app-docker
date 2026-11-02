<?php

namespace App\Http\Controllers\Api\Field;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Field\ResolveFieldPriceRequest;
use App\Http\Requests\Field\StoreFieldPriceRequest;
use App\Http\Requests\Field\UpdateFieldPriceRequest;
use App\Models\Field;
use App\Models\FieldPrice;
use App\Services\FieldPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FieldPriceController extends ApiController
{
    public function __construct(private readonly FieldPriceService $priceService) {}

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    /**
     * GET /fields/{field}/prices
     * List all pricing rules for a field.
     */
    public function index(Field $field): JsonResponse
    {
        $prices = FieldPrice::where('field_id', $field->id)
            ->orderByDesc('priority')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return $this->jsonResponse($prices, 'Field prices retrieved successfully.');
    }

    /**
     * POST /fields/{field}/prices
     * Create a new pricing rule for a field.
     */
    public function store(StoreFieldPriceRequest $request, Field $field): JsonResponse
    {
        $data = $request->validated();

        $price = FieldPrice::create([
            'field_id'          => $field->id,
            'pricing_type'      => $data['pricing_type'],
            'day_of_week'       => $data['day_of_week'] ?? null,
            'start_time'        => $data['start_time'] ?? null,
            'end_time'          => $data['end_time'] ?? null,
            'price'             => $data['price'],
            'priority'          => $data['priority'] ?? 1,
            'valid_from'        => $data['valid_from'] ?? null,
            'valid_to'          => $data['valid_to'] ?? null,
            'min_booking_hours' => $data['min_booking_hours'] ?? null,
            'max_booking_hours' => $data['max_booking_hours'] ?? null,
            'slot_duration'     => $data['slot_duration'] ?? 60,
            'is_active'         => $data['is_active'] ?? true,
        ]);

        return $this->jsonResponse($price, 'Pricing rule created successfully.', 201);
    }

    /**
     * GET /fields/{field}/prices/{price}
     * Show a single pricing rule.
     */
    public function show(Field $field, FieldPrice $price): JsonResponse
    {
        $this->abortIfNotOwnedByField($price, $field);

        return $this->jsonResponse($price, 'Pricing rule retrieved successfully.');
    }

    /**
     * PUT /fields/{field}/prices/{price}
     * Update a pricing rule.
     */
    public function update(UpdateFieldPriceRequest $request, Field $field, FieldPrice $price): JsonResponse
    {
        $this->abortIfNotOwnedByField($price, $field);

        $price->update($request->validated());

        return $this->jsonResponse($price->fresh(), 'Pricing rule updated successfully.');
    }

    /**
     * DELETE /fields/{field}/prices/{price}
     * Delete a pricing rule.
     */
    public function destroy(Field $field, FieldPrice $price): JsonResponse
    {
        $this->abortIfNotOwnedByField($price, $field);

        $price->delete();

        return $this->jsonResponse(null, 'Pricing rule deleted successfully.');
    }

    // ─── Toggle ───────────────────────────────────────────────────────────────

    /**
     * PATCH /fields/{field}/prices/{price}/toggle
     * Enable or disable a pricing rule.
     */
    public function toggle(Field $field, FieldPrice $price): JsonResponse
    {
        $this->abortIfNotOwnedByField($price, $field);

        $price->update(['is_active' => ! $price->is_active]);

        $status = $price->is_active ? 'enabled' : 'disabled';

        return $this->jsonResponse($price->fresh(), "Pricing rule {$status} successfully.");
    }

    // ─── Price Resolution ─────────────────────────────────────────────────────

    /**
     * GET /fields/{field}/prices/resolve?date=Y-m-d&start_time=H:i&end_time=H:i
     *
     * Resolve the best matching price rule for a given booking date/time window
     * and return the applicable rule + calculated totals.
     */
    public function resolve(ResolveFieldPriceRequest $request, Field $field): JsonResponse
    {
        $data = $request->validated();

        $rule = $this->priceService->resolve(
            $field->id,
            $data['date'],
            $data['start_time'],
            $data['end_time'],
        );

        if (! $rule) {
            return $this->jsonResponse(null, 'No active pricing rule found for the requested slot.', 404);
        }

        $durationError = $this->priceService->validateDuration($rule, $data['start_time'], $data['end_time']);

        if ($durationError) {
            return response()->json([
                'status'  => 422,
                'message' => $durationError,
                'results' => null,
            ], 422);
        }

        if ($rule->pricing_type === 'daily') {
            $calculation = $this->priceService->calculate($rule, $data['start_time'], $data['end_time']);

            return $this->jsonResponse([
                'price'    => $calculation['total'],
                'currency' => $rule->currency,
            ], 'Price resolved successfully.');
        }

        // For hourly pricing, split the window into per-slot segments so that
        // bookings spanning multiple pricing windows (e.g. off-peak → peak)
        // are billed segment by segment using each segment's matching rule.
        $range = $this->priceService->calculateRange(
            $field->id,
            $data['date'],
            $data['start_time'],
            $data['end_time'],
            $rule->slot_duration ?: 60,
        );

        return $this->jsonResponse([
            'price'    => $range['total'],
            'currency' => $range['currency'] ?? $rule->currency,
        ], 'Price resolved successfully.');
    }

    /**
     * GET /fields/{field}/prices/grid?date=Y-m-d&slot_duration=60
     *
     * Return a full-day pricing grid with per-slot prices.
     */
    public function grid(Request $request, Field $field): JsonResponse
    {
        $request->validate([
            'date'          => ['required', 'date_format:Y-m-d', 'date'],
            'slot_duration' => ['sometimes', 'integer', 'in:15,30,45,60,90,120'],
        ]);

        $slots = $this->priceService->dailyGrid(
            $field,
            $request->input('date'),
            (int) $request->input('slot_duration', 60),
        );

        return $this->jsonResponse($slots, 'Daily pricing grid retrieved successfully.');
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function abortIfNotOwnedByField(FieldPrice $price, Field $field): void
    {
        if ($price->field_id !== $field->id) {
            abort(404, 'Pricing rule not found for this field.');
        }
    }
}
