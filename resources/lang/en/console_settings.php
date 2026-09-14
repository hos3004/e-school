<?php

declare(strict_types=1);

return [
    'green_api' => [
        'title' => 'WhatsApp connection via Green API',
        'description' => 'Enter the API URL, instance ID and token from Green API. Enabling tests the connection before saving. The token is never shown again.',
        'api_url' => 'API URL',
        'instance_id' => 'Instance ID',
        'token' => 'Instance token',
        'token_keep' => 'Leave blank to keep the stored token.',
        'token_new' => 'Paste apiTokenInstance from Green API.',
        'enabled' => 'Enable sending after connection test',
        'reason' => 'Reason for changing this connection',
        'status' => 'Connection status',
        'authorized' => 'Enabled',
        'state_attention' => 'Instance needs attention',
        'disabled' => 'Disabled',
        'webhook_ready' => 'Incoming replies configured',
        'webhook_pending' => 'Incoming replies not configured yet',
        'token_required' => 'Enter an instance token before enabling.',
        'connection_failed' => 'The instance could not be verified or is not authorized.',
        'save' => 'Test connection and save',
        'register_webhook' => 'Enable incoming replies and statuses',
        'webhook_failed' => 'Could not configure Green API webhooks. Check the connection and public site URL.',
        'webhook_saved' => 'Incoming webhooks configured. The instance may take a few minutes to restart.',
        'reason_required' => 'Enter a reason first.',
        'webhook_notice' => 'Enabling webhooks restarts the Green API instance for a few minutes.',
        'saved' => 'WhatsApp connection saved after verification.',
    ],
];
