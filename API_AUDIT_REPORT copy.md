# API RECOVERY & PRODUCTION FIX — SPORTAPP

You are working on the SportApp Laravel API.

I have an API audit report that identified missing, broken, and potentially lost code after a previous VPS/SSH incident.

Your job is to inspect the existing codebase and restore the missing functionality **without breaking existing functionality or changing the architecture unnecessarily**.

---

## 1. IMPORTANT RULES

Before modifying anything:

1. Read and understand the existing Laravel architecture.
2. Inspect the relevant:

   * routes
   * controllers
   * models
   * requests/validation
   * migrations
   * policies/permissions
   * resources
   * services
   * existing sibling methods
3. Do NOT blindly implement methods based only on the API documentation.
4. Follow the existing coding patterns already used in the project.
5. Reuse existing services, models, relationships, validation and authorization logic whenever possible.
6. Do NOT duplicate business logic.
7. Do NOT remove working functionality.
8. Do NOT change database schema unless absolutely required.
9. Do NOT modify unrelated files.
10. Do NOT change API response formats unless the existing implementation clearly requires it.
11. Do NOT create fake/stub implementations just to make routes stop throwing errors.
12. Every implemented endpoint must perform the real expected operation.
13. Preserve existing authentication and authorization rules.
14. Never bypass permissions, ownership checks, or security mechanisms.
15. Do not commit or push anything.

---

# 2. SOURCE OF TRUTH

Use this priority when deciding how functionality should work:

1. Existing working code
2. Existing models/relationships/database schema
3. Existing request validation
4. Existing sibling controller methods
5. Existing API routes
6. API documentation

If the documentation conflicts with the current architecture, DO NOT blindly change the architecture.

Explain the conflict first.

---

# 3. CRITICAL ISSUES TO FIX

The audit found these controller methods referenced by routes but missing:

### AdminPlayerController

Missing:

```php
updatePlayer()
updatePlayerPicture()
```

### AdminVenueController

Missing:

```php
updateAmenities()
updateFields()
getFieldsByFormat()
```

These are CRITICAL because the routes already reference these methods and currently result in fatal errors.

---

# 4. TASK A — RESTORE updatePlayer()

Find the route corresponding to:

```http
PATCH/POST /admin/players/{id}
```

Inspect:

* `AdminPlayerController`
* player/user creation logic
* existing user update logic
* `User` model
* validation requests
* relationships
* permissions
* dashboard frontend expectations
* API documentation

Implement:

```php
AdminPlayerController::updatePlayer()
```

Requirements:

* Load the correct player/user.
* Validate input using the project's existing validation conventions.
* Update only fields that are intended to be editable by the admin.
* Preserve existing password handling.
* Preserve existing team/player relationships.
* Preserve authorization.
* Return the same response structure used by similar admin endpoints.
* Handle validation errors correctly.
* Handle missing users correctly.
* Do not introduce duplicate business logic.

Before implementing, identify the closest existing method that should be used as the implementation pattern.

---

# 5. TASK B — RESTORE updatePlayerPicture()

Restore:

```php
AdminPlayerController::updatePlayerPicture()
```

Route:

```http
POST /admin/players/{id}/picture
```

Inspect the existing user picture/profile-picture implementation first.

Reuse the same:

* upload mechanism
* storage disk
* file validation
* filename conventions
* old-image deletion/replacement behavior
* database fields
* response format

Do NOT create a second image-upload system.

The admin endpoint should behave consistently with the existing user picture update functionality.

---

# 6. TASK C — RESTORE updateAmenities()

Restore:

```php
AdminVenueController::updateAmenities()
```

Route:

```http
PUT /admin/venues/{id}/amenities
```

Before implementing:

* inspect the Venue model
* inspect the Amenity model
* inspect pivot tables
* inspect venue creation logic
* inspect how amenities are attached during venue creation
* inspect any existing attach/sync/detach logic

The update should correctly synchronize the venue's amenities.

Prefer the existing Laravel relationship methods such as:

```php
sync()
```

when appropriate.

Do not manually manipulate pivot records if the existing architecture already uses Eloquent relationships.

Preserve:

* authorization
* validation
* transaction behavior where appropriate
* response format

---

# 7. TASK D — RESTORE getFieldsByFormat()

Restore:

```php
AdminVenueController::getFieldsByFormat()
```

Route:

```http
GET /admin/venues/{id}/fields
```

The audit indicates that the endpoint is expected to retrieve venue fields.

Inspect:

* `Venue`
* `Field`
* `FieldFormat`
* relationships
* existing field listing methods
* venue creation logic

Determine exactly how fields are associated with a venue.

Do NOT invent a new relationship.

Return the fields according to the project's existing API response conventions.

If the endpoint is expected to group/filter fields by format, follow the existing database relationships and frontend expectations.

---

# 8. TASK E — RESTORE updateFields()

Restore:

```php
AdminVenueController::updateFields()
```

Route:

```http
PUT /admin/venues/{id}/fields
```

This is potentially the most complex of the five methods.

Before coding, inspect:

* `Field` model
* `FieldFormat`
* Venue → Fields relationship
* price relationships
* availability relationships
* field creation/update requests
* venue creation transaction
* existing field update logic
* migrations

Determine how the application currently models:

```text
Venue
 └── Fields
      ├── Field Format
      ├── Surface
      ├── Dimensions
      ├── Prices
      └── Availability
```

Do NOT redesign this structure.

Implement the update using the existing architecture.

If the request updates multiple fields, make sure:

* validation is performed correctly
* existing fields are updated
* new fields are created only if the current API contract allows it
* removed fields are handled according to the existing business logic
* related prices/availability are preserved or updated correctly
* ownership/authorization is checked
* database operations are transactional if multiple related records are changed

Do not delete existing records unless the API contract explicitly requires deletion.

---

# 9. SECONDARY ISSUES

After fixing the 5 CRITICAL methods, inspect these issues.

## A. Default Team Logos

Expected:

```http
GET /teams/default-logos
```

The audit found:

```text
DefaultLogo model exists
but no route/controller endpoint exists
```

Inspect `DefaultLogo`.

If the model and database structure clearly support this feature, implement:

```php
GET /teams/default-logos
```

using the project's existing response conventions.

Do not create unnecessary architecture.

---

## B. My Fields

Expected:

```http
GET /fields/my
```

Inspect:

* Field
* Venue
* authenticated user
* venue ownership relationship

Implement only if the ownership relationship is clearly established by the existing schema.

The endpoint must return fields belonging to venues owned/managed by the authenticated user according to the project's actual authorization model.

Do not guess ownership based only on column names.

---

## C. Venue Gallery Deletion

Expected:

```http
DELETE /venues/{venue}/gallery/{media}
```

Inspect existing gallery upload logic first.

If implemented:

* verify venue ownership/authorization
* delete the database media record
* delete the associated physical file according to the project's storage architecture
* preserve existing gallery behavior
* handle missing media correctly

Do not leave orphaned files.

---

# 10. NOTIFICATION FEATURE

The audit reports that:

```http
GET /notifications
```

is not implemented at all.

There is currently no:

* route
* NotificationController
* notifications table
* database notification channel

Do NOT implement this automatically unless the existing project clearly requires it.

First inspect the notification architecture.

If implementation is required, report the required architecture and files before making large changes.

Potential components include:

```text
notifications migration
NotificationController
database notification channel
notification persistence
route
```

But do not assume these are required without inspecting the codebase.

---

# 11. REALTIME / REVERB

Inspect the current broadcasting configuration.

The audit reports:

```text
laravel/reverb exists
UserNotificationEvent exists
but .env.example uses:

BROADCAST_CONNECTION=log

and does not contain REVERB_* variables.
```

Do not change production broadcasting configuration automatically.

Determine whether Reverb is actually expected by the application.

If it is required, report the exact configuration needed.

---

# 12. DOCUMENTATION MISMATCHES

There are known documentation mismatches:

### Create Field

Documentation suggests fields contain:

```text
address
city
latitude
longitude
surface
status
```

while the actual validation expects:

```text
venue_id
field_format_id
surface_type
width_m
length_m
```

This suggests the documentation may be stale.

DO NOT redesign the Field model to match stale documentation.

---

### Field Reviews

Documentation mentions:

```http
POST /fields/{field}/reviews
GET /fields/{field}/reviews/summary
```

But the current architecture uses venue reviews.

There is evidence that this was an intentional architecture change.

Therefore:

DO NOT reintroduce field reviews unless the existing code/business requirements clearly require them.

---

# 13. Admin Players team_id FILTER

Inspect:

```http
GET /admin/players
```

The documentation contains:

```text
team_id
```

but the controller currently appears to support only:

```text
search
```

Determine whether the frontend actually requires `team_id`.

If required, implement it using the existing relationships and query conventions.

Do not add unnecessary filtering logic.

---

# 14. TESTING REQUIREMENTS

After implementation, verify every modified endpoint.

At minimum:

### Admin Player

```text
Update player
Update player picture
```

### Admin Venue

```text
Get venue fields
Update venue fields
Update venue amenities
```

### Secondary

```text
Default logos
My fields
Gallery deletion
```

Test:

* successful request
* validation failure
* unauthorized request
* resource not found
* invalid IDs
* malformed payload
* empty payload where relevant
* duplicate relationships
* file upload failures where relevant

---

# 15. REGRESSION CHECK

Before finishing:

1. Run the existing Laravel test suite if available.
2. Run:

```bash
php artisan route:list
```

3. Confirm the five critical routes resolve to existing controller methods.
4. Run PHP syntax checks where appropriate.
5. Check for obvious Laravel errors.
6. Check that no existing endpoint was unintentionally changed.
7. Review the final git diff.

DO NOT commit or push changes.

---

# 16. REQUIRED FINAL REPORT

When finished, do NOT simply say "done".

Give me a structured report:

## A. Root Cause

Explain why each critical endpoint was broken.

## B. Files Changed

List every modified/created file.

## C. Changes

For every file, explain exactly what was changed.

## D. Endpoints Restored

Example:

```text
POST /admin/players/{id}          ✅
POST /admin/players/{id}/picture  ✅
PUT  /admin/venues/{id}/amenities ✅
GET  /admin/venues/{id}/fields    ✅
PUT  /admin/venues/{id}/fields    ✅
```

## E. Tests

Show the tests/checks performed and their results.

## F. Remaining Issues

Clearly list anything that still requires manual work.

## G. Risk Assessment

Identify any implementation where the existing codebase was ambiguous and explain the assumption made.

---

# MOST IMPORTANT

This is a RECOVERY task.

Do not redesign the application.

Do not "clean up" unrelated code.

Do not change working endpoints.

Do not bypass authentication, authorization, licensing, or security.

Restore the missing functionality using the project's existing architecture and patterns.

Start by inspecting the codebase and producing a short implementation plan.

Only then modify the code.
