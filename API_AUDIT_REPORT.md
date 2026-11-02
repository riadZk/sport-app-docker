# API Audit Report — SportApp

Audited against: https://api-docs.hoppscotch.io/view/72a309a2-355e-403f-b7f3-2e8a333204dd/CURRENT
Date: 2026-09-13

No code was modified as part of this audit.

## 1. Implemented (matches documentation)

| Area | Endpoints | Notes |
|---|---|---|
| **Auth** | signup, login, login_by_email, verify-login-otp, resend-login-otp, verify-signup-otp, resend-signup-otp, logout | All present in `routes/api.php` and `AuthController.php` |
| **Teams** | Create/List/Show/Update Team, Update Team Logo | `routes/Api/Team.php` → `TeamController` — fully implemented, including invitation bundling on creation |
| **Team Invitations** | Invite, bulk-invite, join-request, invitations index, my invitations, respond, cancel | `routes/Api/TeamInvitation.php` → `TeamInvitationController` — complete |
| **Player Stats** | All, mine, team stats, update position | `routes/Api/PlayerStat.php` — matches doc exactly |
| **User** | Profile, update info, update picture, change password, request/verify phone change | `routes/Api/User.php` — complete |
| **Fields/Venues (map/listing/detail/format/availability/closures)** | `/venues/map`, `/venues/listing`, `/venues/{id}`, `/venues/{id}/fields-by-format`, `/venues/{id}/availabilities`, `/venues/{id}/closures`, `/venues/fields/{field}/availability`, `/fields/{field}/prices/resolve` | `routes/Api/Venue.php`, `routes/Api/Field.php` — implemented |
| **Venue Reviews** | store/update/delete/summary | `VenueReviewController.php` — rating validated 1–5 correctly |
| **Dashboard Admin — Auth/Teams/Bookings/Amenities-list/Field-formats-list** | admin/login, admin/teams (all), admin/bookings, admin/amenities, admin/field-formats | Fully implemented |
| **Dashboard Admin — Venue create (7-step transaction)** | `POST /admin/venues` | `AdminVenueController::store` — owner+venue+media+amenities+fields+prices+availability, fully implemented |
| **Booking** | create (with conflict check), list, detail, cancel | `BookingController.php` — implemented, contact fields match doc |

## 2. Missing

### a) `POST /admin/players/{id}` — Update User (admin)
- **Expected:** Update a player's profile/stats from the dashboard.
- **Missing:** Route exists (`Route::post('/players/{id}', ...'updatePlayer')`) but `AdminPlayerController::updatePlayer()` does not exist. Confirmed via PHP reflection — calling this endpoint throws a fatal `Error: Call to undefined method`.
- **Files involved:** `app/Http/Controllers/Api/DashboardAdmin/Player/AdminPlayerController.php`, `routes/Api/DashboardAdmin.php`

### b) `POST /admin/players/{id}/picture` — Update Picture User (admin)
- **Expected:** Upload/replace a player's picture.
- **Missing:** `AdminPlayerController::updatePlayerPicture()` does not exist (route references it).
- **Files:** same as above.

### c) `PUT /admin/venues/{id}/amenities` — Update Amenities venue
- **Missing:** `AdminVenueController::updateAmenities()` does not exist.
- **Files:** `app/Http/Controllers/Api/DashboardAdmin/Venue/AdminVenueController.php`

### d) `GET /admin/venues/{id}/fields` and `PUT /admin/venues/{id}/fields` — Get/Update Fields venue
- **Missing:** `AdminVenueController::getFieldsByFormat()` and `updateFields()` do not exist.
- **Files:** same.

### e) `GET /teams/default-logos` — Get Default Logos
- **Expected:** List selectable default team logos (`DefaultLogo` model already exists in `app/Models/DefaultLogo.php`).
- **Missing:** No route in `routes/Api/Team.php`, no controller method to list them. Strong signal of a lost feature — the model exists but nothing wires it to an endpoint.

### f) `GET /fields/my` — Get My Fields
- **Missing:** No such route in `routes/Api/Field.php`. `FieldController` has no venue-owner-scoped listing method.

### g) `POST /fields/{field}/reviews` and `GET /fields/{field}/reviews/summary`
- **Missing:** Reviews only exist at `/venues/{venue}/reviews/...`. There is no field-level review route/controller.
- **Context:** migration `2026_06_06_140000_replace_field_reviews_with_venue_reviews_table.php` shows this was an intentional architecture change (field reviews → venue reviews). This is stale documentation, not necessarily lost code — but still a doc/API mismatch.

### h) `DELETE /venues/{venue}/gallery/{media}` — Delete Gallery Venue
- **Missing:** No such route anywhere (checked both `routes/Api/Venue.php` and `routes/Api/DashboardAdmin.php`). Only *add*-gallery endpoints exist; there's no way to remove a single gallery image.

### i) `GET /notifications` — Get All Notifications
- **Missing — entire feature layer, not just the route:**
  - No route registered anywhere in `routes/api.php`.
  - No `NotificationController`.
  - No `notifications` database table/migration (Laravel's default notifications table was never created).
  - `User` model uses the `Notifiable` trait (`app/Models/User.php`) but the app's actual notification system (`BaseNotification` / `NotificationService`) is a custom class hierarchy that only supports `whatsapp` and `realtime` (Reverb broadcast) channels — there is no `database`/persistence channel at all, so even if a controller were added, there'd be nothing to query.
  - **Files that would need to be created:** migration for `notifications` table (or a custom `notifications` table), a `database` channel in `NotificationService`, a `NotificationController`, and a route.

## 3. Incomplete / Broken

| Endpoint | Issue |
|---|---|
| `POST /fields` (Create Field) | Doc body implies fields have their own `address/city/latitude/longitude/surface/status` — but actual `StoreFieldRequest` requires `venue_id`, `field_format_id`, `surface_type`, `width_m`/`length_m` (fields belong to venues, no location of their own). This is a doc/schema mismatch — either the docs are stale or a "standalone field creation" flow was dropped when Venues were introduced. |
| `GET /admin/players` (search) | Doc shows a `team_id` filter parameter; controller (`getPlayers`) only implements `search`, no `team_id` filtering. |
| Realtime notifications (Reverb) | `laravel/reverb` is in `composer.json`, and `UserNotificationEvent` uses `ShouldBroadcastNow` + `PrivateChannel`, but `.env.example` has `BROADCAST_CONNECTION=log` and no `REVERB_*` env vars exist at all. Realtime notifications are effectively no-ops in the current default configuration. |
| Admin "Update User" HTTP verb | Documented as `PATCH`, implemented as `POST` (multipart, likely intentional for file uploads) — cosmetic but worth confirming with frontend team. |

## 4. Possible Lost Changes (evidence found)

1. **Confirmed via PHP reflection** — 5 controller methods referenced by routes do not exist:
   - `AdminPlayerController::updatePlayer`
   - `AdminPlayerController::updatePlayerPicture`
   - `AdminVenueController::updateAmenities`
   - `AdminVenueController::updateFields`
   - `AdminVenueController::getFieldsByFormat`

   This is the strongest evidence of the SSH changes being lost — these are exactly the kind of "last mile" CRUD handlers someone writes right after scaffolding the store/list/delete methods, and they're the ones missing.
2. **`DefaultLogo` model exists with no consumer** — a model was created but the endpoint using it (`/teams/default-logos`) was apparently never finished or was lost.
3. Uncommitted local working-tree diffs (checked via `git diff`) were investigated as a possible clue, but they turned out to be environment-only tweaks (Telescope provider disabled locally, `.env.example` APP_URL, idempotent migration guard) — not related to the missing API code above.

## 5. Priority

| Priority | Item |
|---|---|
| CRITICAL | `AdminPlayerController::updatePlayer` missing (fatal error in production) |
| CRITICAL | `AdminPlayerController::updatePlayerPicture` missing (fatal error) |
| CRITICAL | `AdminVenueController::updateAmenities` missing (fatal error) |
| CRITICAL | `AdminVenueController::updateFields` missing (fatal error) |
| CRITICAL | `AdminVenueController::getFieldsByFormat` missing (fatal error) |
| HIGH | Notifications feature entirely absent (route, controller, table, persistence channel) |
| MEDIUM | `GET /teams/default-logos` missing |
| MEDIUM | `GET /fields/my` missing |
| MEDIUM | `DELETE /venues/{venue}/gallery/{media}` missing |
| MEDIUM | Reverb/broadcasting env vars absent — realtime channel silently broken |
| LOW | Field-level reviews doc vs venue-level reviews implementation (likely stale docs) |
| LOW | `admin/players` missing `team_id` filter |
| LOW | Create Field doc body vs actual validation mismatch (likely stale docs) |

## 6. Recovery Plan (ordered)

1. Restore the 5 missing admin controller methods (`updatePlayer`, `updatePlayerPicture`, `updateAmenities`, `updateFields`, `getFieldsByFormat`) — these are guaranteed production 500 errors right now. Use the sibling methods in the same controllers (`updateVenue`, `createPlayer`, etc.) as a pattern reference, and the documented request bodies as the field/validation spec.
2. Decide the fate of `/teams/default-logos` — implement a controller method (`DefaultLogo::all()` or similar) and route since the model already exists.
3. Implement `/fields/my` — likely `Field::where('venue.created_by', auth()->id())` or similar venue-owner scoping.
4. Implement gallery-image deletion for venues (`DELETE /venues/{venue}/gallery/{media}` and probably an admin equivalent).
5. Decide the Notifications feature scope — if it's needed, add: `notifications` table/migration, a `database` channel in `NotificationService`, a `NotificationController` + route, and update each `*Notification` class's channel list.
6. Configure Reverb properly (add `REVERB_*` env vars, set `BROADCAST_CONNECTION=reverb`) if realtime notifications are actually required in production.
7. Reconcile "Create Field" and "field reviews" documentation with current schema (either update the Hoppscotch docs or decide if standalone field creation/field-reviews need to be reintroduced).
8. Add `team_id` filter support to `GET /admin/players` if still needed by the frontend.
