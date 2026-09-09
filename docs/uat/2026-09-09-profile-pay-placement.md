# 2026-09-09 — Profile completion, teacher remuneration, and approved placement

All implementation, tests and deployment run on SSH SCHOOL-WEB. Worktree: /opt/eschool-worktrees/profile-completion-session-pay-20260909. Production: /opt/eschool. No WSL or I:\\e-school edits or synchronization.

Students and teachers with incomplete profiles are gated on the web and authenticated API until they confirm genuine email, international phone, country, governorate/region, city, DOB and timezone. Regions without catalog data support manual entry. Contact changes clear verification. Administration retains normal access.

Settings manage versioned session types, durations and teacher remuneration in EGP: group/children 35 minutes = 3125 minor units; individual/adults 25 = 2500; optional individual 40 = 3350. These are teacher pay, not student billing. Teacher-specific rates retain precedence, prices resolve at lesson time, and ledger snapshots remain immutable. New school pay contracts cover the 17 imported teachers.

Approved workbook: all adults 25 minutes, all children 35 minutes, Europe/Istanbul. User confirmed 7.5 means 19:30 and all ambiguous Maryam-teacher times are PM. Result: 18 adults, 49 weekly slots. Materialization rehearsal generated 425 future sessions with one participant each. Remaining 55 students with known teachers receive durable, soft-deletable pending teaching links, shown in teacher roster and adult console without invented times. Three children have no teacher in source. One merged adult has two source teachers; both pending source links are preserved until a timetable resolves assignment. Known-teacher enrollments activate through the official audited placement gateway.

Validation:
- Full suite initially: 1387 passing / 26 failing out of 1413. Failures traced to incomplete legacy fixtures and production environment flags; all affected tests rerun successfully (139 passing, 3504 assertions).
- Payroll/settings final: 16 passing, 60 assertions; preserves old ledger after rate change.
- Pending links + learning/library/architecture: 100 passing, one missing ownership registration; registered Scheduling ownership, final 89 passing / 2693 assertions.
- Console placement: 16 passing / 326 assertions including pending teacher visibility/filtering.
- TypeScript, ESLint, Vite production build, changed-file Pint and targeted PHPStan pass. Whole-project PHPStan has 851 existing errors; untouched production baseline independently has the same 851.
- Browser: forced student desktop and teacher mobile completion, required contact confirmation, settings save/reload, no JavaScript errors or overflow.
- Populated database clone: profile migration up/rollback/reapply; pending-link migration; initialized rates/contracts and approved placements. Repeated operations create no duplicate contracts, links, schedules or sessions. Verification checks each generated local weekday/time, duration, teacher and participant, plus teacher conflicts.

Private backup: /opt/eschool-backups/profile-pay-20260909/ contains pre-change database, environment/assets, source revision, baseline static analysis and rehearsal reports.
Private operational scripts and manifest remain outside Git in storage/app/private; no passwords or personal workbook data are committed.
