<?php

declare(strict_types=1);

/*
| Business-rule error messages — Audit module — English.
| Consumed via BusinessRuleViolation::make('code', 'audit::errors.key').
*/

return [

    'reason_required' => 'The action ":action" is sensitive and requires a written reason on the entry.',

    'action_required' => 'An audit entry cannot be recorded without an action.',
];
