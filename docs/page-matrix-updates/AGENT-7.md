# AGENT 7 — Notifications, Recordings, and Messaging handoff

## Implemented / verified

- Notification bell is implemented as a reusable component with unread polling, recent notifications, mark-as-read, loading/error/empty states, and a configurable full-page URL: `resources/js/Components/NotificationBell.tsx`.
- Shared notification center uses the existing notification API for list, individual read, and mark-all-read actions with translated states and optional target links: `resources/js/Pages/Shared/Notifications.tsx`.
- `NotificationOutboxResource` already has tenant scoping, status/channel/read filters, index actions, and a registered view page. The view page displays delivery attempts chronologically and uses the existing retry/cancel actions; hardcoded UI strings were removed from its owned view page.
- `RecordingResource` has index/view-only pages, tenant scoping, recording details, active grants, view logs, grant-access action, and delete-with-reason action. Hardcoded duration fallback was removed.

## Verification

- PHP syntax checks and Pint pass for the changed Filament view pages and existing resource files.
- No new Comms test directory existed to execute. The current repository's isolated suites for Notifications/Recordings/Messaging API behavior remain the source of truth and should be run with `TEST_AGENT_ID=codex7`.
- TypeScript compilation requires the repository Node dependencies; it was not available in the host shell during this pass.

## Known issues / explicit boundaries

- Routes for portal notification pages, messaging pages, and the recording player are owned by the coordinator and were not changed.
- Signed temporary recording URLs and the protected playback endpoint are not available in the inspected backend contract; no public URL was fabricated. This remains a K3 backend gap.
- Recording grant UI currently accepts a recipient user id directly; a searchable recipient picker needs an approved directory endpoint before it can be safely added.
- Existing shared Messaging pages are present but still contain legacy hardcoded copy and need a focused translation/UX pass; no backend or cross-role access was widened here.
- Conversation/Message/ClassWall/WhatsappInbound Filament resources were not converted beyond their existing ownership because no additional moderation actions were exposed by the backend contract.

## Route/nav proposals (not applied)

```php
Route::get('/student/notifications', fn () => Inertia::render('Shared/Notifications', ['role' => 'student']))->name('portal.student.notifications');
Route::get('/teacher/notifications', fn () => Inertia::render('Shared/Notifications', ['role' => 'teacher']))->name('portal.teacher.notifications');
Route::get('/guardian/notifications', fn () => Inertia::render('Shared/Notifications', ['role' => 'guardian']))->name('portal.guardian.notifications');
```

Suggested nav targets for the coordinator: `/notifications`, `/messages`, `/messages/create`, and `/recordings` where the corresponding portal/backend contract exists.

## Matrix proposals

- L1 Notification Bell: `MISSING` → `FUNCTIONAL` after AppLayout integration and browser proof.
- L2/L3 Notification center/deep links: `MISSING` → `FUNCTIONAL` after portal route registration and API HTTP proof.
- L4/L5/L6 Delivery log/details/failed filter: `PARTIAL`/`MISSING` → `FUNCTIONAL` after Filament route smoke test.
- L7 Manual retry/cancel: `PARTIAL` → `FUNCTIONAL` after admin action smoke test.
- K1 Recordings list: `PARTIAL` → `FUNCTIONAL`; K2/K4/K5/K6 → `FUNCTIONAL` after Filament smoke test.
- K3 remains `MISSING` pending a signed playback endpoint.
- P1–P3 remain `MISSING UI` pending route integration and translation/access pass.
