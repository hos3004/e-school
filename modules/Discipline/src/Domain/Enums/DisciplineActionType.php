<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\Enums;

/**
 * إجراءات سُلَّم التصعيد — قيمها مطابقة لمصفوفة config('discipline.ladder').
 * الموديول لا يقرر الإجراء بنفسه؛ يطبّق ما يقوله الإعداد.
 */
enum DisciplineActionType: string
{
    /** تنبيه أول. */
    case Notice = 'notice';

    /** إنذار رسمي بوجود تجميد وشيك. */
    case Warning = 'warning';

    /** تجميد تسجيل الطالب — ينفّذه موديول Enrollments مستمعًا للحدث. */
    case FreezeEnrollment = 'freeze_enrollment';

    /** مراجعة عقد المعلم — مسار انضباط المعلمين. */
    case ContractReview = 'contract_review';

    /**
     * قراءة الإجراء من سطر السُلَّم في الإعدادات.
     *
     * @param  array<string, mixed>  $ladderEntry
     */
    public static function fromLadderEntry(array $ladderEntry): ?self
    {
        $action = $ladderEntry['action'] ?? null;

        return is_string($action) ? self::tryFrom($action) : null;
    }

    public function label(): string
    {
        return __('discipline::actions.types.'.$this->value);
    }
}
