# 🏟️ Sports App — Database Structure

> This file is a Copilot reference for all database tables, relationships, and rules.

---

## 📁 Tables Overview

| Table | Description | Managed by |
|---|---|---|
| `venues` | Sports complex (City Foot) | App |
| `field_formats` | Format types (5vs5, 7vs7, 11vs11) | App |
| `fields` | Individual fields inside a venue | App |
| `field_prices` | Dynamic pricing rules per field | App |
| `field_availabilities` | Recurring weekly opening hours per field | App |
| `field_closures` | One-off blackouts / maintenance / events | App |
| `field_bookings` | Actual reservations consuming availability | App |
| `media` | Images (cover + gallery) per field | Spatie |

---

## 📁 Migration Order

```
1. venues
2. field_formats
3. fields
4. field_prices
5. field_availabilities
6. field_closures
7. field_bookings
8. media (auto — Spatie MediaLibrary)
```

---

## 1️⃣ Table: `venues`

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint | ❌ | auto | Primary key |
| `name` | varchar(100) | ❌ | — | Complex name |
| `address` | varchar(255) | ✅ | null | Street address |
| `city` | varchar(100) | ✅ | null | City name |
| `phone` | varchar(20) | ✅ | null | Contact phone |
| `latitude` | decimal(10,8) | ✅ | null | GPS latitude (-90 to +90) |
| `longitude` | decimal(11,8) | ✅ | null | GPS longitude (-180 to +180) |
| `is_active` | boolean | ❌ | true | Enable/disable |
| `created_at` | timestamp | ✅ | null | — |
| `updated_at` | timestamp | ✅ | null | — |

---

## 2️⃣ Table: `field_formats`

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint | ❌ | auto | Primary key |
| `name` | varchar(50) | ❌ | — | "5vs5", "7vs7", "11vs11" |
| `players_per_team` | integer | ❌ | — | 5, 7, 11 |
| `description` | text | ✅ | null | Optional description |
| `created_at` | timestamp | ✅ | null | — |
| `updated_at` | timestamp | ✅ | null | — |

---

## 3️⃣ Table: `fields`

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint | ❌ | auto | Primary key |
| `venue_id` | bigint (FK) | ❌ | — | → venues.idf CASCADE |
| `field_format_id` | bigint (FK) | ❌ | — | → field_formats.id RESTRICT |
| `name` | varchar(100) | ❌ | — | "Field A", "Field 5v5-B" |
| `surface_type` | enum | ❌ | artificial | grass / artificial / futsal |
| `width_m` | decimal(5,2) | ✅ | null | Width in meters |
| `length_m` | decimal(5,2) | ✅ | null | Length in meters |
| `is_indoor` | boolean | ❌ | false | Indoor/outdoor |
| `is_active` | boolean | ❌ | true | Enable/disable |
| `created_at` | timestamp | ✅ | null | — |
| `updated_at` | timestamp | ✅ | null | — |

### Indexes
- `index('venue_id')`
- `index('field_format_id')`

> ❌ No `price_per_hour` — pricing lives in `field_prices`
> ❌ No `image_url` — images live in Spatie `media`

---

## 4️⃣ Table: `field_prices`

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint | ❌ | auto | Primary key |
| `field_id` | bigint (FK) | ❌ | — | → fields.id CASCADE |
| `label` | varchar | ✅ | null | "Weekend", "Ramadan", "Peak Hours" |
| `pricing_type` | enum | ❌ | hourly | hourly / daily |
| `day_of_week` | enum | ✅ | null | monday…sunday — null = all days |
| `start_time` | time | ✅ | null | Start of time range |
| `end_time` | time | ✅ | null | End of time range |
| `price` | decimal(10,2) | ❌ | — | Price amount |
| `currency` | varchar(3) | ❌ | DZD | ISO currency code |
| `priority` | integer | ❌ | 1 | Higher = wins on conflict |
| `valid_from` | date | ✅ | null | Seasonal start date |
| `valid_to` | date | ✅ | null | Seasonal end date |
| `min_booking_hours` | integer | ✅ | null | Min booking duration |
| `max_booking_hours` | integer | ✅ | null | Max booking duration |
| `slot_duration` | integer | ❌ | 60 | Slot size in minutes (30, 60, 90...) |
| `is_active` | boolean | ❌ | true | Enable/disable rule |
| `created_at` | timestamp | ✅ | null | — |
| `updated_at` | timestamp | ✅ | null | — |

### Index
```
field_prices_resolution_index → [field_id, day_of_week, start_time, end_time, priority]
```

### Priority Resolution (highest wins)
```
Priority 4 → Seasonal / Ramadan      (valid_from + valid_to set)
Priority 3 → Peak hours              (day_of_week + start_time + end_time set)
Priority 2 → Weekend / specific day  (day_of_week set only)
Priority 1 → Default fallback        (everything null)
```

---

## 5️⃣ Table: `field_availabilities`

Recurring **weekly opening hours** per field (when the field is open for booking).

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint | ❌ | auto | Primary key |
| `field_id` | bigint (FK) | ❌ | — | → fields.id CASCADE |
| `day_of_week` | enum | ❌ | — | monday…sunday |
| `start_time` | time | ❌ | — | Opens at |
| `end_time` | time | ❌ | — | Closes at |
| `valid_from` | date | ✅ | null | Seasonal start (e.g. Ramadan) |
| `valid_to` | date | ✅ | null | Seasonal end |
| `is_active` | boolean | ❌ | true | Enable/disable |
| `created_at` | timestamp | ✅ | null | — |
| `updated_at` | timestamp | ✅ | null | — |

### Indexes
- `index(['field_id', 'day_of_week'])`
- `index(['valid_from', 'valid_to'])`

---

## 6️⃣ Table: `field_closures`

One-off **blackouts / exceptions** (maintenance, private events, holidays) that override availability.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint | ❌ | auto | Primary key |
| `field_id` | bigint (FK) | ❌ | — | → fields.id CASCADE |
| `starts_at` | datetime | ❌ | — | Closure start |
| `ends_at` | datetime | ❌ | — | Closure end |
| `reason` | varchar(255) | ✅ | null | "Maintenance", "Tournament" |
| `created_at` | timestamp | ✅ | null | — |
| `updated_at` | timestamp | ✅ | null | — |

### Indexes
- `index(['field_id', 'starts_at', 'ends_at'])`

---

## 7️⃣ Table: `field_bookings`

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

---

## 🧠 Booking & Availability Logic

The bookable slots of a field are computed by **layering** four sources in order.
Each layer can only **remove** time from the previous one (open → narrow → block → consume).

```
field_availabilities   →  WHEN the field is OPEN     (recurring weekly, per day)
        ↓ minus
field_closures         →  one-off EXCEPTIONS         (maintenance, events, holidays)
        ↓ minus
field_bookings         →  already RESERVED slots     (pending / confirmed)
        ↓ equals
        =  FREE bookable slots
```

### Step 1 — Weekly availability (days)
For a requested **date**, resolve its `day_of_week` (e.g. `2026-06-10` → `wednesday`)
and load every active `field_availabilities` row that matches:

```
field_id      = :field
day_of_week   = :weekday
is_active     = true
AND (valid_from IS NULL OR valid_from <= :date)   -- seasonal window
AND (valid_to   IS NULL OR valid_to   >= :date)
```

- A field may have **multiple rows per day** (e.g. `08:00–12:00` and `16:00–23:00`).
- `valid_from` / `valid_to` allow a **seasonal override** (e.g. Ramadan hours)
  that only applies inside its date window.

### Step 2 — Exceptions / closures (subtract)
Remove any time that overlaps an active `field_closures` row for that date:

```
field_id   = :field
starts_at  <  :slot_end
ends_at    >  :slot_start          -- standard overlap test
```

Closures use full `datetime` (not weekday), so they handle:
holidays, maintenance windows, private tournaments, or a single-evening blackout.

### Step 3 — Existing bookings (subtract)
Remove time already taken by `field_bookings` where `status IN (pending, confirmed)`
using the same overlap test. `cancelled` and `completed` do **not** block.

### Step 4 — Slot generation
Split the remaining open ranges into slots using the matching
`field_prices.slot_duration` (default `60` min), honoring
`min_booking_hours` / `max_booking_hours`. Each generated slot is priced via the
**highest `priority` wins** rule (see §4).

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
Field 7 — Wednesday 2026-06-10
  availability : 08:00–12:00, 16:00–23:00
  closure      : 17:00–18:30 (maintenance)
  bookings     : 19:00–20:00 (confirmed)

  OPEN    → 08:00–12:00 , 16:00–23:00
  − close →            , 16:00–17:00 , 18:30–23:00
  − book  →            , 16:00–17:00 , 18:30–19:00 , 20:00–23:00
  = FREE  → 08:00–12:00 , 16:00–17:00 , 18:30–19:00 , 20:00–23:00
```

---
## 8️⃣ Table: `media` — Spatie (auto)

```bash
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```

### Collections on `Field`
| Collection | Type | Notes |
|---|---|---|
| `cover` | singleFile | Main cover image |
| `gallery` | multiple | Gallery images |

### Conversions on `Field`
| Conversion | Size | Used for |
|---|---|---|
| `thumb` | 400×300 | Map popup, cards |
| `card` | 800×600 | Field detail page |

---

## 🔗 Relationships

```
venues
  └── hasMany ──► fields
                    ├── belongsTo ──► field_formats
                    ├── hasMany   ──► field_prices
                    ├── hasMany   ──► field_availabilities
                    ├── hasMany   ──► field_closures
                    ├── hasMany   ──► field_bookings
                    └── hasMedia  ──► media (Spatie)
                                       ├── cover   (singleFile)
                                       └── gallery (multiple)

users ──< field_bookings
teams ──< field_bookings (nullable)
field_prices ──< field_bookings (snapshot of rule used)
```

---

## ✅ Models Summary

```php
// Venue
public function fields(): HasMany          // → Field::class

// Field  (implements HasMedia)
public function venue(): BelongsTo         // → Venue::class
public function format(): BelongsTo        // → FieldFormat::class
public function prices(): HasMany          // → FieldPrice::class
// collections : cover (singleFile), gallery
// conversions : thumb (400×300), card (800×600)

// FieldFormat
public function fields(): HasMany          // → Field::class

// FieldPrice
public function field(): BelongsTo         // → Field::class
```

---

## 📌 Copilot Rules

- **Never** add `price_per_hour` to `fields` → use `field_prices`
- **Never** add `image_url` to `fields` → use Spatie `media`
- Price resolution always uses **highest `priority` wins**
- GPS precision: `decimal(10,8)` for lat, `decimal(11,8)` for lng
