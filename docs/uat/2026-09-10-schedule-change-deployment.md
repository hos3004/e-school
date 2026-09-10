# Permanent schedule change — production deployment, 2026-09-10

## Release

Published `f10ac34` on SCHOOL-WEB (`/opt/eschool`) from `707d9a3`, following the user's explicit deployment request. Fast-forward only; tracked production files were clean. Existing production Compose/FPM configuration was preserved.

Recovery point: `/opt/eschool-admin-backups/20260910T192446Z-e62a68`. The backup contains the repository bundle, code/storage/configuration archive and PostgreSQL dump. The supplied backup tool verified archive contents and restored the database successfully in an isolated temporary container. This is an online backup, not an atomic database/filesystem snapshot.

Built the assets in the feature worktree and copied the build to production, retaining old hashed assets. TypeScript and Vite passed; Pint passed for 3034 files. Production autoload was regenerated. Both new migrations applied successfully, then configuration/routes/views were cached and app/Horizon/scheduler restarted. The application exited maintenance mode successfully.

Added only missing global notification templates for `schedule.change.requested`, `schedule.change.applied` and `schedule.change.rejected`: 18 templates across Arabic/English and in-app/email/WhatsApp. Existing templates were not overwritten and no test messages were sent. All 18 templates rendered successfully using synthetic parameters.

## Live checks

- Home and login: HTTPS 200.
- `/learn/teacher/schedule` and `/learn/student/schedule`: unauthenticated requests redirect to authentication (302).
- New request/respond/withdraw routes are registered.
- Horizon is running; scheduler and application containers are running.
- Every file referenced by the deployed asset manifest exists, including both schedule bundles.
- Before/after record counts unchanged: users 96, students 77, staff 18, sessions 426, payroll entries 0.
- New request and approval tables each contain zero rows; both new permissions exist.
- Focused isolated Scheduling suite: 17 tests passed, 201 assertions, including approval/rejection and migration checks.

## Verification limitations

The full static-analysis gate remains non-green: the saved production baseline has 851 errors; this verification returned 875. All 24 additional diagnostics are Pest TestCall method inference in the new test file (actingAs/seed/withoutVite), with no additional application-code diagnostics. No suppression or baseline change was introduced. Therefore this report does not claim a clean composer check.

No authenticated visual browser journey was performed for this deployment. Portal behavior is covered by the feature tests; HTTP checks above are smoke checks. CI and coverage thresholds were not verified. Expired requests still close lazily on attempted response; no periodic expiry worker was added.

## Recovery

If needed, take another recovery point and revert the feature code in an isolated branch, rebuild assets, and redeploy using the same production lock. The additive database tables can remain when reverting code. Do not roll back/drop request tables or restore the old production database after new requests have been accepted without reviewing data loss. Restore old assets from the verified archive if an asset rollback is required.

## Final regression result

Full isolated suite completed successfully: **1428 passed, 11801 assertions**, duration 597.65 seconds, seed 1789068273. This includes the architecture suite. Tests ran against a generated database on the independent worktree PostgreSQL container. No production test fixtures or test notifications were created.
