<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Contracts;

use Modules\Notifications\Domain\ValueObjects\NotificationAdministrationData;

interface NotificationAdministrationQueries
{
    /** @return list<NotificationAdministrationData> */
    public function forSession(string $organizationId, string $sessionId): array;
}
