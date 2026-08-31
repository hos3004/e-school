<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\ValueObjects;

/** اشتراك أحداث مسجّل عند المزوّد، بلا تفاصيل خاصة به. */
final readonly class RegisteredWebhook
{
    public function __construct(
        public string $hookId,
        public string $callbackUrl,
        public ?string $externalId = null,
        public bool $permanent = false,
    ) {}
}
