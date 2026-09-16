<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Services;

use Modules\Notifications\Domain\Contracts\EmailDeliverabilityCheck;

final readonly class EmailDeliverabilityGuard implements EmailDeliverabilityCheck
{
    public function __construct(private UndeliverableEmailDomains $undeliverable) {}

    public function isDeliverable(?string $email): bool
    {
        if (!is_string($email) || trim($email) === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        return !$this->undeliverable->isUndeliverable($email);
    }
}
