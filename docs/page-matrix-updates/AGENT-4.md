# AGENT 4 — Student portal handoff

## Implemented

- Added tenant-scoped student profile, enrolled-program, and active-group read queries to `PortalData`.
- Added `StudentProfileController`, `StudentProgramsController`, and `StudentGroupController`; all return only the authenticated student's records within their organization.
- Reworked the three Student pages to use the shared `AppLayout` and UI contracts, translation keys, and explicit loading/empty/error states with retry.
- Added Arabic and English `student.profile`, `student.programs`, and `student.group` translation keys.

## Verification

- PHP syntax checks passed for all three controllers and `PortalData`.
- Pint `--test` passed for the four PHP files.
- No Student page-completion test directory exists yet, so no isolated page suite could be run. TypeScript verification requires the repository Node dependencies (`npm run types` could not find `tsc` in the current host environment).

## Route integration proposal (not applied per ownership rules)

Add these routes under the existing authenticated student portal group, with `can:student.view` (or the established student portal middleware):

```php
Route::get('/student/profile', StudentProfileController::class)->name('portal.student.profile');
Route::get('/student/programs', StudentProgramsController::class)->name('portal.student.programs');
Route::get('/student/group', StudentGroupController::class)->name('portal.student.group');
```

The student navigation entries must be added by the owner of `resources/js/Layouts/AppLayout.tsx`.

## Matrix proposals

- M2 Student profile: `MISSING` → `FUNCTIONAL` after route registration and HTTP proof.
- M3 Student programs: `MISSING` → `FUNCTIONAL` after route registration and HTTP proof.
- M4 Student group: `MISSING` → `FUNCTIONAL` after route registration and HTTP proof.

Student availability (M7) was not invented: no student-availability domain/API was found in the inspected codebase and it remains a backend-gap item.
