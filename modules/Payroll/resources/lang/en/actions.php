<?php

declare(strict_types=1);

/*
| Action messages — consumed via BusinessRuleViolation::make inside Application\Actions.
| The key describes the rule, not the wording.
*/

return [
    'record_entry' => [
        'unknown_outcome' => 'The session outcome ":outcome" is not defined in the payroll outcomes matrix.',
        'currency_mismatch' => 'Entry currency ":currency" does not match the platform currency.',
        'period_not_found' => 'The requested payroll period was not found.',
        'period_closed' => 'The period is in status ":status" and no longer accepts new entries.',
        'duplicate' => 'An entry already exists for this session, staff member, and entry type.',
    ],
    'propose_adjustment' => [
        'reference_period_not_found' => 'The referenced payroll period was not found in this organization.',
        'staff_not_found' => 'The selected staff profile was not found in this organization.',
        'unknown_type' => 'Adjustment type ":type" is not among the allowed types.',
        'reason_required' => 'An adjustment requires a clearly written reason.',
        'invalid_amount' => 'Invalid adjustment amount: it must be non-zero; bonuses positive and deductions negative.',
        'period_not_found' => 'The requested payroll period was not found.',
        'period_frozen' => 'The period is financially frozen (status ":status") and accepts no adjustments.',
    ],
    'approve_adjustment' => [
        'not_found' => 'The requested payroll adjustment was not found in this organization.',
        'already_decided' => 'This adjustment has already been decided and cannot be approved again.',
        'period_frozen' => 'The adjustment period is financially frozen and accepts no approvals.',
        'self_approval' => 'Whoever proposed the adjustment cannot approve it — another supervisor must.',
    ],
    'reject_adjustment' => [
        'not_found' => 'The requested payroll adjustment was not found in this organization.',
        'already_decided' => 'This adjustment has already been decided and cannot be rejected again.',
        'reason_required' => 'Rejecting an adjustment requires a clearly written reason.',
        'period_frozen' => 'The adjustment period is financially frozen.',
        'self_approval' => 'Whoever proposed the adjustment cannot reject it — another supervisor must.',
    ],
    'release_deferred' => [
        'none' => 'No deferred entries are linked to makeup session :makeup_session_id.',
        'invalid_transition' => 'Entry :entry_id is in status ":from" and cannot be released.',
    ],
    'settle_makeup' => [
        'manual' => 'The makeup session was taught by a teacher other than the one holding the deferred entry, so settlement is an administrative decision.',
        'released' => 'Deferred earning released after the makeup session was held.',
        'fallback' => 'The makeup session was held with no deferred entry on the original, so a single entry was recorded at the original session rate.',
    ],
    'backfill_postponements' => [
        'reason' => 'Backfill for a postponement that never produced a deferred entry.',
        'not_recorded' => 'No entry was created for this postponement - check the warning log (unresolved rate or closed period).',
        'summary' => 'eligible: :planned - written: :written - skipped: :skipped - failed: :failed',
        'dry_run' => 'Dry run: nothing was written. Re-run with --execute to write.',
    ],
    'rate_unresolved' => [
        'reason' => 'No applicable rate for this teacher, session type and duration, so no payroll entry was created.',
    ],
];
