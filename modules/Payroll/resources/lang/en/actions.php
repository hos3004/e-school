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
        'unknown_type' => 'Adjustment type ":type" is not among the allowed types.',
        'reason_required' => 'An adjustment requires a clearly written reason.',
        'invalid_amount' => 'Invalid adjustment amount: it must be non-zero; bonuses positive and deductions negative.',
        'period_not_found' => 'The requested payroll period was not found.',
        'period_frozen' => 'The period is financially frozen (status ":status") and accepts no adjustments.',
    ],
    'approve_adjustment' => [
        'already_decided' => 'This adjustment has already been decided and cannot be approved again.',
        'period_frozen' => 'The adjustment period is financially frozen and accepts no approvals.',
        'self_approval' => 'Whoever proposed the adjustment cannot approve it — another supervisor must.',
    ],
    'reject_adjustment' => [
        'already_decided' => 'This adjustment has already been decided and cannot be rejected again.',
        'reason_required' => 'Rejecting an adjustment requires a clearly written reason.',
        'period_frozen' => 'The adjustment period is financially frozen.',
        'self_approval' => 'Whoever proposed the adjustment cannot reject it — another supervisor must.',
    ],
    'release_deferred' => [
        'none' => 'No deferred entries are linked to makeup session :makeup_session_id.',
        'invalid_transition' => 'Entry :entry_id is in status ":from" and cannot be released.',
    ],
];
