<?php

namespace App\Services;

use App\Models\Field;
use App\Models\FieldPrice;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FieldPriceService
{
    /**
     * Resolve the single best-matching price rule for a field on a specific
     * date and time range.
     *
     * Resolution order (highest wins):
     *   1. is_active = true
     *   2. Valid on the given calendar date (seasonal rules included)
     *   3. Matches the day-of-week (or has no day restriction)
     *   4. Time window overlaps the booking range (or has no time restriction)
     *   5. Among all remaining candidates → highest `priority` wins
     *      (ties broken by most-specific rule: has day_of_week > no day,
     *       has time range > no time range, has valid_from > no date range)
     *
     * @param  int    $fieldId
     * @param  string $date      Y-m-d
     * @param  string $startTime H:i  (24-hour)
     * @param  string $endTime   H:i  (24-hour)
     * @return FieldPrice|null
     */
    public function resolve(int $fieldId, string $date, string $startTime, string $endTime): ?FieldPrice
    {
        $dayOfWeek = Carbon::parse($date)->dayName; // "Monday", "Tuesday" …
        $dayOfWeek = strtolower($dayOfWeek);

        /** @var Collection<int, FieldPrice> $candidates */
        $candidates = FieldPrice::where('field_id', $fieldId)
            ->active()
            ->validOn($date)
            ->forDay($dayOfWeek)
            ->forTimeRange($startTime, $endTime)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates->sortByDesc(function (FieldPrice $rule) {
            // primary sort: explicit priority value
            $score = $rule->priority * 1000;

            // secondary sort: prefer seasonal over global
            $score += $rule->isSeasonal() ? 100 : 0;

            // tertiary sort: prefer day-specific over any-day
            $score += $rule->day_of_week ? 10 : 0;

            // quaternary sort: prefer time-specific over any-time
            $score += $rule->start_time ? 1 : 0;

            return $score;
        })->first();
    }

    /**
     * Calculate the total price for a booking given a resolved rule.
     *
     * @param  FieldPrice $rule
     * @param  string     $startTime  H:i
     * @param  string     $endTime    H:i
     * @return array{price: float, hours: float, slots: int, pricing_type: string}
     */
    public function calculate(FieldPrice $rule, string $startTime, string $endTime): array
    {
        $start = Carbon::createFromFormat('H:i', $startTime);
        $end   = Carbon::createFromFormat('H:i', $endTime);

        $minutes = $start->diffInMinutes($end);
        $hours   = $minutes / 60;
        $slots   = (int) ceil($minutes / $rule->slot_duration);

        $total = match ($rule->pricing_type) {
            'hourly' => round($rule->price * $hours, 2),
            'daily'  => (float) $rule->price,
            default  => round($rule->price * $hours, 2),
        };

        return [
            'pricing_type' => $rule->pricing_type,
            'price_per_unit' => (float) $rule->price,
            'hours'  => $hours,
            'slots'  => $slots,
            'slot_duration_minutes' => $rule->slot_duration,
            'total'  => $total,
        ];
    }

    /**
     * Calculate the total price for a booking range by splitting it into
     * per-slot segments, resolving the best rule for each segment, and
     * summing each segment's cost.
     *
     * This correctly handles bookings that span multiple pricing windows
     * (e.g. an off-peak rule until 18:00 then a peak rule from 18:00).
     *
     * @return array{
     *     total: float,
     *     currency: string|null,
     *     hours: float,
     *     segments: array<int, array{start: string, end: string, hours: float, price_per_hour: float, subtotal: float, rule_id: int|null}>
     * }
     */
    public function calculateRange(int $fieldId, string $date, string $startTime, string $endTime, int $slotMinutes = 60): array
    {
        $start = Carbon::createFromFormat('H:i', $startTime);
        $end   = Carbon::createFromFormat('H:i', $endTime);

        $totalMinutes = $start->diffInMinutes($end);
        $segments     = [];
        $total        = 0.0;
        $currency     = null;

        $cursor = $start->copy();

        while ($cursor->lt($end)) {
            $segmentEnd = $cursor->copy()->addMinutes($slotMinutes);
            if ($segmentEnd->gt($end)) {
                $segmentEnd = $end->copy();
            }

            $segMinutes = $cursor->diffInMinutes($segmentEnd);
            $segHours   = $segMinutes / 60;

            $rule = $this->resolve(
                $fieldId,
                $date,
                $cursor->format('H:i'),
                $segmentEnd->format('H:i'),
            );

            $pricePerHour = $rule ? (float) $rule->price : 0.0;
            $subtotal     = round($pricePerHour * $segHours, 2);

            $segments[] = [
                'start'          => $cursor->format('H:i'),
                'end'            => $segmentEnd->format('H:i'),
                'hours'          => $segHours,
                'price_per_hour' => $pricePerHour,
                'subtotal'       => $subtotal,
                'rule_id'        => $rule?->id,
            ];

            $total += $subtotal;
            $currency = $currency ?? $rule?->currency;

            $cursor = $segmentEnd;
        }

        return [
            'total'    => round($total, 2),
            'currency' => $currency,
            'hours'    => $totalMinutes / 60,
            'segments' => $segments,
        ];
    }

    /**
     * Validate that the requested booking duration respects the rule's
     * min/max booking hours constraints.
     *
     * @return string|null  Error message, or null if valid.
     */
    public function validateDuration(FieldPrice $rule, string $startTime, string $endTime): ?string
    {
        $start   = Carbon::createFromFormat('H:i', $startTime);
        $hours   = $start->diffInMinutes(Carbon::createFromFormat('H:i', $endTime)) / 60;

        if ($rule->min_booking_hours !== null && $hours < $rule->min_booking_hours) {
            return "Minimum booking duration is {$rule->min_booking_hours} hour(s).";
        }

        if ($rule->max_booking_hours !== null && $hours > $rule->max_booking_hours) {
            return "Maximum booking duration is {$rule->max_booking_hours} hour(s).";
        }

        return null;
    }

    /**
     * Return all active pricing rules for a field, grouped by type.
     *
     * @return array{hourly: Collection, daily: Collection}
     */
    public function allForField(int $fieldId): array
    {
        $rules = FieldPrice::where('field_id', $fieldId)
            ->active()
            ->orderByDesc('priority')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return [
            'hourly' => $rules->where('pricing_type', 'hourly')->values(),
            'daily'  => $rules->where('pricing_type', 'daily')->values(),
        ];
    }

    /**
     * Build an availability & pricing grid for a given date.
     * Returns time slots with the price that applies to each slot.
     *
     * @param  Field  $field
     * @param  string $date   Y-m-d
     * @param  int    $slotMinutes  default 60
     * @return Collection<int, array{slot_start: string, slot_end: string, price: float|null, rule_id: int|null}>
     */
    public function dailyGrid(Field $field, string $date, int $slotMinutes = 60): Collection
    {
        $slots = collect();
        $current = Carbon::parse($date)->setTime(0, 0);
        $endOfDay = $current->copy()->setTime(23, 59);

        while ($current->lt($endOfDay)) {
            $slotEnd = $current->copy()->addMinutes($slotMinutes);

            $rule = $this->resolve(
                $field->id,
                $date,
                $current->format('H:i'),
                $slotEnd->format('H:i'),
            );

            $slots->push([
                'slot_start' => $current->format('H:i'),
                'slot_end'   => $slotEnd->format('H:i'),
                'price'      => $rule ? (float) $rule->price : null,
                'rule_id'    => $rule?->id,
                'pricing_type' => $rule?->pricing_type,
            ]);

            $current->addMinutes($slotMinutes);
        }

        return $slots;
    }
}
