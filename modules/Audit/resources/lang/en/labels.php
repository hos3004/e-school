<?php

declare(strict_types=1);

/*
| Audit module labels — English.
*/

return [

    'nav_group' => 'Governance & Security',
    'nav_sort' => 'Audit Log',

    'audit_log' => [
        'label' => 'audit entry',
        'plural' => 'Audit Log',
        'view_title' => 'View audit entry',
    ],

    'fields' => [
        'action' => 'Action',
        'actor_type' => 'Actor type',
        'actor' => 'Actor',
        'acting_for' => 'Acting on behalf of',
        'auditable_type' => 'Record type',
        'auditable_id' => 'Record ID',
        'old_values' => 'Values before',
        'new_values' => 'Values after',
        'reason' => 'Reason',
        'ip_address' => 'IP address',
        'correlation_id' => 'Correlation ID',
        'created_at' => 'Recorded at',
    ],

    'sections' => [
        'context' => 'Actor context',
        'subject' => 'Subject',
        'changes' => 'Changes & reason',
        'metadata' => 'Technical metadata',
    ],

    'actor_types' => [
        'user' => 'User',
        'system' => 'System',
        'integration' => 'Integration',
    ],

    'actions' => [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'restored' => 'Restored',
        'force_deleted' => 'Force deleted',
        'logged_in' => 'Signed in',
        'logged_out' => 'Signed out',
        'permission_changed' => 'Permissions changed',
    ],
];
