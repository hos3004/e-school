# Console v2: people, reports and shared settings

This delivery runs only behind the new console feature flag and authenticated console routes. Existing admin routes and production data are unchanged.

## People

- Real student and teacher directories with organization-scoped search, pagination, archived records, and preserved directory context when opening a record.
- Server-authorized create, edit, and printable administrative profiles. Account name, phone and timezone changes additionally require UserPolicy update on the organization-scoped account. A real custom role test proves profile-only editors cannot change identity data while retaining academic editing. Teacher contract/rate and guardian sections obey their additional read permissions.
- Student creation composes the existing onboarding/admission actions; teacher creation composes existing account, profile, contract, rate and qualification actions. Failures roll back their shared transaction.
- New or existing account mode, server-checked username suggestions, strong password generation in the browser, explicit labels and inline validation. Editing identity data does not change the username, email or password.
- Country timezone suggestions use IANA zones. The initial timezone comes from the organization; selecting a country with multiple zones requires an explicit choice.
- Routine updates send truthful action descriptions to audit services without requiring a typed reason.
- Existing identity services do not support clearing a saved phone number. This form explicitly rejects such a request instead of claiming that a silent no-op succeeded; replacement remains supported.

## Reports and settings repairs

- Session date and time are both displayed in the selected timezone, avoiding a UTC date next to a local time.
- Report pagination uses server URLs that retain array filters and the original teacher. All supported filter categories are visible; invalid GET criteria redirect to a clean report URL.
- A custom date range is submitted only when the custom period is selected.
- General school setting saves use a locked transaction and a fingerprint of the relevant stored fields. A second stale edit is rejected even when both writes occur in the same second; the UI retains the draft and offers an explicit action to adopt current saved values.
- The institution calendar week start is visible as read-only. Reporting retains its existing configured week policy until all engines are unified.
- Only the Arabic school name and timezone are editable here. Existing translated names and the stored organization locale are preserved server-side, with no additional language fields in the console form. Other policy settings are shown through an explicit allowlist; secrets and feature overrides are not exposed or accepted.

## Verified

`ConsolePeopleTest` and `ConsoleWorkspaceTest`: **19 tests, 285 assertions passed** using `scripts/test-isolated.php` in the isolated console Docker stack.

The people components and shared report/settings components passed ESLint and the project TypeScript check. The temporary Learning/Profile.tsx useForm.defaults issue was resolved during integration. Changed PHP controllers/requests passed PHPStan; Pint formatted the owned PHP and translation files.

Coverage includes cross-organization denial, permission separation, disabled console entry points, username collisions, atomic onboarding, preserving credentials, local-day report boundaries, retaining report filters, audit events, settings scope and stale write rejection.

## Design alignment

People forms reuse the shared console-control, console-button, field, field-grid, form-section, setup-layout and summary classes. A single panel groups the sections, using the demo field sizes and spacing from the root stylesheet.

## Remaining scope

This is an administrative people slice, not a claim that the entire replacement dashboard is complete. Editing established teacher contracts, historical rates, qualifications, guardian relationships, attachments and profile images still needs dedicated workflows. User/teacher learning portals are delivered separately. The shared settings policy fields remain read-only until their owning actions and validation are connected. Browser and print layout QA, full architecture checks, and final integration are coordinated by the parent implementation task.

Subsequent integration added working account prefix, calendars, holidays and notification category editors to the general settings page; see docs/console-v2-implementation.md for the current scope and verification.
