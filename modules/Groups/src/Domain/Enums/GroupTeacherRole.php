<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\Enums;

/**
 * دور المعلم داخل المجموعة — تصنيف وظيفي لا حالة زمنية.
 */
enum GroupTeacherRole: string
{
    /** المعلم الأساسي المسؤول عن المادة. */
    case Lead = 'lead';

    /** معلم مساعد. */
    case Assistant = 'assistant';

    /** معلم تلافي يغطي غياب المعلم الأساسي. */
    case Substitute = 'substitute';

    public function label(): string
    {
        return __('groups::status.teacher_role.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Lead => 'blue',
            self::Assistant => 'sky',
            self::Substitute => 'amber',
        };
    }
}
