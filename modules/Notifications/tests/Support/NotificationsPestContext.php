<?php

declare(strict_types=1);

namespace Modules\Notifications\Tests\Support;

use Tests\TestCase;

/**
 * Static-analysis contract for notification fixtures supplied by Pest traits.
 */
abstract class NotificationsPestContext extends TestCase
{
    public string $organizationId;

    abstract public function createSessionParticipant(): string;
}
