<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Enums;

/**
 * دور المشارك داخل الفصل المباشر.
 *
 * المشرف (المعلم/الإدارة) يملك تحكم الصوت والعرض؛
 * المتلقي (الطالب) يدخل كمشاهد فقط.
 */
enum JoinRole: string
{
    case Moderator = 'moderator';

    case Viewer = 'viewer';

    /** هل هذا الدور يملك صلاحيات إدارة الفصل؟ */
    public function isModerator(): bool
    {
        return $this === self::Moderator;
    }

    public function label(): string
    {
        return __('virtualclassroom::join_roles.'.$this->value);
    }
}
