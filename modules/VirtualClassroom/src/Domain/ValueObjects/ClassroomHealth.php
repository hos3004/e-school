<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\ValueObjects;

use Modules\VirtualClassroom\Domain\Enums\ClassroomHealthStatus;

/**
 * نتيجة فحص صحة المزوّد.
 */
final readonly class ClassroomHealth
{
    public function __construct(
        public ClassroomHealthStatus $status,
        public ?string $message = null,
    ) {}
}
