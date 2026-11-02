# 📅 Sports App — Booking & Availability

> Copilot reference for the booking subsystem: availability, exceptions, and reservations.

---

## 📁 Tables Overview

| Table | Description | Managed by |
|---|---|---|
| `venue_availabilities` | Recurring weekly **open hours** per `day_of_week` for the venue, optional seasonal window | App |
| `venue_closures` | One-off **exceptions** (maintenance, events, holidays) for the venue via full datetime | App |
| `field_bookings` | Already-reserved slots (`pending`/`confirmed` block; `cancelled`/`completed` don't) | App |

---

## 📁 Migration Order

```
1. venue_availabilities
2. venue_closures
3. field_bookings   (depends on users, teams, field_prices)
```

---

## 1️⃣ Table: `venue_availabilities`

Recurring **weekly opening hours** per venue — when the venue is open for booking.
Applies to all fields in the venue. Optional `valid_from` / `valid_to` window for
**seasonal** overrides (e.g. Ramadan hours).

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint | ❌ | auto | Primary key |
| `venue_id` | bigint (FK) | ❌ | — | → venues.id CASCADE |
| `day_of_week` | enum | ❌ | — | monday…sunday |
| `start_time` | time | ❌ | — | Opens at |
| `end_time` | time | ❌ | — | Closes at |
| `valid_from` | date | ✅ | null | Seasonal start (e.g. Ramadan) |
| `valid_to` | date | ✅ | null | Seasonal end |
| `is_active` | boolean | ❌ | true | Enable/disable |
| `created_at` | timestamp | ✅ | null | — |
| `updated_at` | timestamp | ✅ | null | — |

### Indexes
- `index(['venue_id', 'day_of_week'])`
- `index(['valid_from', 'valid_to'])`

### Rules
- A venue may have **multiple rows per day** (e.g. `08:00–12:00` and `16:00–23:00`).
- A row with `valid_from` / `valid_to` only applies **inside** that date window.
- Rows with both dates `null` are the **default** all-year hours.

---

## 2️⃣ Table: `venue_closures`

One-off **exceptions / blackouts** (maintenance, private events, holidays) that
override availability for the whole venue. Uses full `datetime`, not weekday.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint | ❌ | auto | Primary key |
| `venue_id` | bigint (FK) | ❌ | — | → venues.id CASCADE |
| `starts_at` | datetime | ❌ | — | Closure start |
| `ends_at` | datetime | ❌ | — | Closure end |
| `reason` | varchar(255) | ✅ | null | "Maintenance", "Tournament" |
| `created_at` | timestamp | ✅ | null | — |
| `updated_at` | timestamp | ✅ | null | — |

### Indexes
- `index(['venue_id', 'starts_at', 'ends_at'])`

### Rules
- A closure **subtracts** time from availability for the overlapping range.
- Applies to **all fields** in the venue (whole complex closed).
- Handles holidays, maintenance windows, private tournaments, single-evening blackouts.

---

## 3️⃣ Table: `field_bookings`

Actual **reservations** that consume availability slots.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint | ❌ | auto | Primary key |
| `field_id` | bigint (FK) | ❌ | — | → fields.id RESTRICT |
| `user_id` | bigint (FK) | ❌ | — | → users.id RESTRICT |
| `team_id` | bigint (FK) | ✅ | null | → teams.id SET NULL |
| `starts_at` | datetime | ❌ | — | Booking start |
| `ends_at` | datetime | ❌ | — | Booking end |
| `duration_minutes` | integer | ❌ | — | Cached length |
| `status` | enum | ❌ | pending | pending / confirmed / cancelled / completed |
| `price_total` | decimal(10,2) | ❌ | — | Snapshot at booking time |
| `currency` | varchar(3) | ❌ | DZD | — |
| `field_price_id` | bigint (FK) | ✅ | null | → field_prices.id SET NULL (rule used) |
| `notes` | text | ✅ | null | Optional |
| `cancelled_at` | timestamp | ✅ | null | — |
| `created_at` | timestamp | ✅ | null | — |
| `updated_at` | timestamp | ✅ | null | — |

### Indexes
- `index(['field_id', 'starts_at', 'ends_at'])`
- `index(['user_id', 'status'])`
- `unique(['field_id', 'starts_at'])` ← prevents double-booking the same start

### Status rules
| Status | Blocks slot? | Notes |
|---|---|---|
| `pending` | ✅ | Held while awaiting payment/confirmation |
| `confirmed` | ✅ | Active reservation |
| `cancelled` | ❌ | Frees the slot (`cancelled_at` set) |
| `completed` | ❌ | Past booking, no longer blocks |

---

## 🧠 Booking & Availability Logic

The bookable slots of a field are computed by **layering** the three tables in order.
Each layer can only **remove** time from the previous one.

```
venue_availabilities   →  WHEN the venue is OPEN     (recurring weekly, per day)
        ↓ minus
venue_closures         →  one-off EXCEPTIONS         (maintenance, events, holidays)
        ↓ minus
field_bookings         →  already RESERVED slots     (pending / confirmed, per field)
        ↓ equals
        =  FREE bookable slots for that field
```

### Step 1 — Weekly availability (days)
For a requested **date**, resolve its `day_of_week` (e.g. `2026-06-10` → `wednesday`)
and load every active `venue_availabilities` row that matches:

```
venue_id      = :venue
day_of_week   = :weekday
is_active     = true
AND (valid_from IS NULL OR valid_from <= :date)   -- seasonal window
AND (valid_to   IS NULL OR valid_to   >= :date)
```

### Step 2 — Exceptions / closures (subtract)
Remove any time that overlaps an active `venue_closures` row for that date:

```
venue_id   = :venue
starts_at  <  :slot_end
ends_at    >  :slot_start          -- standard overlap test
```

### Step 3 — Existing bookings (subtract)
Remove time already taken by `field_bookings` where `status IN (pending, confirmed)`
using the same overlap test. `cancelled` and `completed` do **not** block.

### Step 4 — Slot generation
Split the remaining open ranges into slots using the matching
`field_prices.slot_duration` (default `60` min), honoring
`min_booking_hours` / `max_booking_hours`. Each slot is priced via the
**highest `priority` wins** rule.

### Overlap rule (used in steps 2 & 3)
Two ranges `[a_start, a_end)` and `[b_start, b_end)` overlap when:

```
a_start < b_end  AND  a_end > b_start
```

### Double-booking protection
- DB level: `unique(['field_id', 'starts_at'])` blocks two bookings at the same start.
- App level: wrap availability-check + insert in a **transaction with a row lock**
  (`lockForUpdate`) to avoid race conditions on concurrent requests.

### Worked example
```
For a field, the venue's open hours and closures are shared across all its fields;
only `field_bookings` are per-field.

Field 7 (Venue 3) — Wednesday 2026-06-10
  venue availability : 08:00–12:00, 16:00–23:00
  venue closure      : 17:00–18:30 (maintenance)
  field 7 bookings   : 19:00–20:00 (confirmed)

  OPEN    → 08:00–12:00 , 16:00–23:00
  − close →             , 16:00–17:00 , 18:30–23:00
  − book  →             , 16:00–17:00 , 18:30–19:00 , 20:00–23:00
  = FREE  → 08:00–12:00 , 16:00–17:00 , 18:30–19:00 , 20:00–23:00
```

---

## 🔗 Relationships

```
venues
  ├── hasMany ──► venue_availabilities
  ├── hasMany ──► venue_closures
  └── hasMany ──► fields
                    └── hasMany ──► field_bookings

users        ──< field_bookings
teams        ──< field_bookings (nullable)
field_prices ──< field_bookings (snapshot of rule used)
```

---

## 📌 Copilot Rules

- Availability and closures live on the **venue** (apply to all its fields).
- Bookings live on the **field** (each field is reserved independently).
- Availability defines **open hours**; closures and bookings only **subtract** from it.
- Only `pending` and `confirmed` bookings block a slot.
- Always check availability **inside a locked transaction** before inserting a booking.
- Snapshot `price_total`, `currency`, and `field_price_id` at booking time.
