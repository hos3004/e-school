<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Enums;

/**
 * نطاق سريان سعر المعلم — من الأخص إلى العام.
 *
 * عند حلّ سعر حصة يُبحث بهذا الترتيب: course ← program ← session_type ← default.
 */
enum RateScope: string
{
    case Course = 'course';

    case Program = 'program';

    case SessionType = 'session_type';

    case Default = 'default';

    /**
     * هل يتطلب هذا النطاق معرّف برنامج؟
     */
    public function requiresProgram(): bool
    {
        return in_array($this, [self::Course, self::Program], true);
    }

    /**
     * هل يتطلب هذا النطاق معرّف مقرر؟
     */
    public function requiresCourse(): bool
    {
        return $this === self::Course;
    }

    public function label(): string
    {
        return __('staff::enums.rate_scope.'.$this->value);
    }
}
