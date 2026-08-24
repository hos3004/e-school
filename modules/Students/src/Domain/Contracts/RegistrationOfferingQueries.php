<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Contracts;

/**
 * منفذ يتحقق من اختيار البرنامج/الكورس دون أن يعرف Students جداول Academics.
 */
interface RegistrationOfferingQueries
{
    public function isAvailable(
        string $organizationId,
        string $programId,
        string $courseId,
    ): bool;
}
