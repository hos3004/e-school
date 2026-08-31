<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Contracts;

use Modules\VirtualClassroom\Domain\ValueObjects\RegisteredWebhook;

/**
 * قدرة اختيارية لمزوّد يدعم تسجيل عنوان استقبال الأحداث برمجيًا.
 *
 * لا تدخل في العقد الأساسي لأن بعض المزوّدين يضبطون webhook من لوحة التحكم.
 */
interface SupportsWebhookRegistration
{
    public function registerWebhook(string $callbackUrl, ?string $externalId = null): RegisteredWebhook;

    /** @return list<RegisteredWebhook> */
    public function registeredWebhooks(?string $externalId = null): array;

    public function removeWebhook(string $hookId): void;
}
