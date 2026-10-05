<?php

declare(strict_types=1);

return [
    'immediate_activation_reason' => 'Activate existing availability under the immediate availability policy without human approval.',
    'organization_required' => 'Specify a valid organization ULID with --organization.',
    'approval_enabled' => 'Disable the availability approval policy before running this command.',
    'preview' => 'Pending periods eligible for activation: :count. No data was changed.',
    'activated' => 'Activated :count availability periods and recorded the changes in the audit log.',
    'full_week_reason_required' => 'Provide the change reason with --reason so it is recorded in the audit log.',
    'actor_invalid' => 'The actor ULID passed with --actor is not valid.',
    'full_week_preview' => ':teachers teachers would receive full availability (:slots windows), replacing :removed existing windows. Nothing was changed.',
    'full_week_applied' => 'Granted 24/7 availability to :teachers teachers by creating :slots windows and replacing :removed earlier windows.',
];
