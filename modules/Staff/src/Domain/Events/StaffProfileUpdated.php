<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Events;

/**
 * تحديث بيانات ملف معلم من مسار الإدارة.
 */
final readonly class StaffProfileUpdated
{
    /**
     * @param array<string, mixed> $changes الحقول المتغيرة بقيمها الجديدة
     */
    public function __construct(
        public string $staffProfileId,
        public string $organizationId,
        public array $changes,
    ) {}
}
