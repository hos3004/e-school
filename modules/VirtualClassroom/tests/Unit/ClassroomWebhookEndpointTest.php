<?php

declare(strict_types=1);

use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Infrastructure\Providers\BigBlueButtonProvider;

it('rejects a callback whose BBB checksum is invalid', function (): void {
    config([
        'virtual-classroom.default' => 'bigbluebutton',
        'virtual-classroom.providers.bigbluebutton' => [
            'driver' => BigBlueButtonProvider::class,
            'base_url' => 'https://bbb.test/bigbluebutton/',
            'secret' => 'api-secret',
            'webhook_secret' => 'webhook-secret',
            'webhook_callback_url' => 'https://eschool.test/api/webhooks/classroom',
            'timeout_seconds' => 10,
            'connect_timeout_seconds' => 5,
            'retry_delays_milliseconds' => [],
            'circuit_breaker' => ['failure_threshold' => 2, 'open_seconds' => 120],
            'supports' => [],
        ],
    ]);
    app()->forgetInstance(VirtualClassroomProvider::class);

    $this->post('/api/webhooks/classroom?checksum=invalid', [
        'event' => '{}',
        'timestamp' => '1720000000000',
    ])->assertUnauthorized();
});
