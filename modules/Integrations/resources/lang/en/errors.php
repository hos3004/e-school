<?php

declare(strict_types=1);

/*
| Error messages of the Integrations module.
| Consumed via __('integrations::errors.key') — keys describe meaning, not wording.
*/

return [

    'provider_not_found' => 'The requested integration provider does not exist (:provider_id).',

    'provider_inactive' => 'The provider ":key" is currently inactive and cannot be linked.',

    'connection_limit_reached' => 'The organization reached its maximum number of connections for this provider (limit: :max).',

    'invalid_status_transition' => 'Cannot move the connection from status ":from" to status ":to".',

    'invalid_delivery_transition' => 'Cannot move the delivery from status ":from" to status ":to".',

    'connection_not_found' => 'The requested connection does not exist (:connection_id).',

    'connection_not_active' => 'The connection is not active (current status: :status) and cannot send messages.',

    'only_dead_can_requeue' => 'Only dead-lettered deliveries can be requeued (current status: :status).',

];
