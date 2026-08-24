# AGENT 6 — Guardian + Public/Auth handoff

## Implemented

- Public registration now loads real countries/regions through `GeographyQueries`, validates the country/region relationship, and persists the current `registration_applications` schema (`full_name`, `country_id`, `region_id`, `submitted_at`, `submitted`).
- Removed the hard-coded invalid organisation fallback. The controller uses an explicit `app.default_organization_id` override or the first organization; it fails clearly when none exists.
- Added duplicate email protection against non-deleted registration applications and a manual IP rate limit (5/minute for submission, 10/minute for status lookup).
- Application status exposes a masked applicant name, status, date, and the ULID reference only. A separate `follow_up_code` migration remains a documented future improvement.
- Replaced the free-text city field with a dependent country/region selector in `RegisterStudent.tsx`.
- Added guardian child overview and schedule controllers/data reads with verified-link and organization scoping. Unauthorized or cross-organization children return 404.
- Replaced the placeholder child overview and schedule screens with translated shared UI and loading/empty/error states.

## Verification

- `RegistrationApplicationTest`: 7 passed, 20 assertions, isolated with `TEST_AGENT_ID=codex6`.
- `PortalRoutesTest`: 22 passed, 157 assertions, isolated with `TEST_AGENT_ID=codex6`.
- PHP syntax checks and Pint passed for changed PHP files and translations.

## Known issues

- Existing route registration is owned by the coordinator; the new child overview/schedule routes and navigation were not applied.
- Notifications page is intentionally left to Agent 7 because its API/UI ownership is shared with the Notifications module.
- The public form currently receives geography in the initial Inertia response; a public `/register/geo` endpoint can be added later if dynamic cacheable loading is preferred.
- The status reference remains the application ULID because migrations are forbidden in this task. A future migration should add a non-guessable `follow_up_code` and stop exposing the ULID.
- The domain enum uses `submitted` (not `pending`) as the valid initial application state; this is retained to satisfy the existing state machine and database constraint.

## Route proposals (not applied)

```php
Route::get('/guardian/children/{studentId}', GuardianChildController::class)->name('portal.guardian.children.show');
Route::get('/guardian/children/{studentId}/schedule', GuardianScheduleController::class)->name('portal.guardian.children.schedule');
```

Add `/guardian/notifications` only with Agent 7's notification endpoint contract. Add child overview/schedule links to the guardian dashboard navigation through the owner of `AppLayout.tsx`.

## Matrix proposals

- A5 Public Student Registration: `PARTIAL` → `TESTED` after a live POST and 429 proof.
- A7 Application Status: `PARTIAL` → `FUNCTIONAL` after route-level 429 proof.
- O3 Child Overview: `PARTIAL` → `FUNCTIONAL` after route registration and verified-link HTTP proof.
- O6 Child Schedule: `MISSING` → `FUNCTIONAL` after route registration and verified-link HTTP proof.
- O7 Notifications remains with Agent 7.
