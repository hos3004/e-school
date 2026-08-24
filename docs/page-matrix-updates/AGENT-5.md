# AGENT 5 — Teacher portal handoff

## Implemented

- Added tenant- and assignment-scoped `PortalData` reads for teacher profile, assigned groups, assigned-group students, and teacher availability.
- Added `TeacherProfileController`, `TeacherGroupsController`, `TeacherStudentsController`, and `TeacherAvailabilityController`.
- Replaced placeholder Profile, Groups, Students, and Availability pages with translated UI using shared layout/components and loading, empty, and error/retry states.
- Availability submission uses the existing `POST /api/staff/availability` contract and includes the authenticated staff profile id; approval remains administrative and is displayed as a status only.

## Verification

- PHP syntax and Pint checks are required before merge; no routes were added because `routes/web.php` is owned by the coordinator.
- Existing `PortalRoutesTest` must be run with `TEST_AGENT_ID=codex5` via the isolated runner. Student-page completion tests for this package do not yet exist.
- TypeScript compilation requires the repository Node dependencies (`tsc` was not available in the host shell during this handoff).

## Known issues / backend gaps

- `teacher_availability` currently constrains approval to `pending|approved`; there is no persisted `rejected` state in the migration, so the UI does not invent one.
- Password editing, notification page, teacher apology submission/own-request tab, and session report/attendance actions already require API/route integration owned by other packages or the coordinator; they remain explicit follow-up items.
- Availability POST currently returns an API resource, while the page uses the existing Inertia form helper; coordinator should verify the response adapter or wire a small JSON client before enabling production submission.

## Route proposals (not applied)

```php
Route::get('/teacher/profile', TeacherProfileController::class)->name('portal.teacher.profile');
Route::get('/teacher/groups', TeacherGroupsController::class)->name('portal.teacher.groups');
Route::get('/teacher/students', TeacherStudentsController::class)->name('portal.teacher.students');
Route::get('/teacher/availability', TeacherAvailabilityController::class)->name('portal.teacher.availability');
```

Navigation entries for the same four pages should be added by the owner of `AppLayout.tsx`.

## Matrix proposals

- N2 My Profile: `MISSING` → `FUNCTIONAL` after route and HTTP proof.
- N3 My Subjects: `MISSING` → `FUNCTIONAL` (read-only specialisations are now visible).
- N4 My Groups: `MISSING` → `FUNCTIONAL` after route and assigned-scope HTTP proof.
- N5 My Students: `MISSING` → `FUNCTIONAL` after route and cross-group 404/empty proof.
- N8 Availability: `MISSING` → `FUNCTIONAL` after API JSON submission proof.
