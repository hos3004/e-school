# 2026-09-09 — Teacher financial visibility

Implemented on SSH SCHOOL-WEB in /opt/eschool-worktrees/teacher-financial-visibility-20260909, starting at production 904d498.

## Behavior
Administration opens a teacher profile under Records / Teachers and changes “Show financial information to this teacher”, with a required reason. StaffProfilePolicy, organization scope, console access and staff.contract.update protect the mutation. The default is visible, preserving existing behavior; no teacher is selected for hiding without the administrator choosing them.

Staff owns financials_visible and registers a Gate restriction for payroll.view/payroll.export/staff.contract.view. Hidden teachers cannot open financial pages or payroll JSON endpoints. Administrators retain financial access. Shared navigation and the learning earnings section honor the effective permission. The setting does not alter rates, contracts, ledger entries or automatic payroll accrual.

Both teacher dashboards show lesson counts independently of payroll: all upcoming scheduled/confirmed lessons, plus completed and cancelled lessons in the displayed local month. UTC start/end bounds are derived from the teacher’s local month. The existing Sessions public query accepts an optional explicit end boundary to avoid timezone and calendar overflow at month edges.

The prior pending-assignment roster now maps legacy non-group session types to the existing individual track kind, preserving teacher profile behavior verified by LearningPortalsTest.

## Workbook analysis
The attached workbook is analyzed without importing or changing its data: 16 names, 45 program/rate rows, 16 phones, no populated emails or fixed salary cells. It includes child individual 35-minute lessons at 31.50 (versus the previously approved 31.25), group 75-minute lessons at 62.50, an adult 25-minute 30.00 exception and 40-minute 33.50 options. One variable program has no fixed rate or duration. Two unmerged blank-name rows are associated with the preceding teacher only in the clearly labeled analytical preview. Source workbook and existing school rates remain unchanged. User receives an Arabic searchable visual preview with source rows.

## Verification
- Seven focused visibility/earnings tests pass: admin scope/reason, self-change rejection, hide/restore, financial web/API denial, independent teacher lesson counts, local-month boundaries and unchanged ledger.
- Learning portal, payroll administration, pending links and architecture regression suite passes.
- Browser on isolated synthetic database: admin setting persists through reload, hidden teacher sees counts with no earnings link, direct earnings request returns 403, restoration works; desktop and 390px layouts have no overflow or JavaScript errors.
- TypeScript and ESLint pass. Production Vite build passes using configLoader runner because shared dependencies are mounted read-only.
- Targeted PHPStan and Pint pass. Full-project baseline static-analysis issues documented in the previous deployment are not suppressed or expanded.
- Migration applies, rolls back and reapplies on the populated isolated production clone, retaining all 17 teachers with visibility enabled.
