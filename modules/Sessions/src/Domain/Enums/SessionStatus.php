<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Enums;

/**
 * دورة حياة الحصة — أهم آلة حالات في المنصة.
 *
 * على هذه الحالة يترتب: الحضور، والمخالفات، ومستحقات المعلم، والتقرير،
 * والتسجيل، والإشعارات. لذلك لا يجوز تغييرها إلا عبر canTransitionTo.
 *
 * المخطط الكامل في docs/05-state-machines.md
 */
enum SessionStatus: string
{
    /** مسوّدة لم تُعلن بعد للطالب أو المعلم. */
    case Draft = 'draft';

    /** مجدولة ومعلنة للطرفين. */
    case Scheduled = 'scheduled';

    /** أكّد الطرفان الحضور. */
    case Confirmed = 'confirmed';

    /** الفصل مفتوح والحصة جارية الآن. */
    case InProgress = 'in_progress';

    /** انتهت الحصة وتنتظر رصد الحضور واعتماد المعلم. */
    case AwaitingReview = 'awaiting_review';

    /** مُقفلة نهائيًا — أُنشئت قيود المستحقات ولا تُعدَّل إلا بتسوية. */
    case Completed = 'completed';

    // ── الحالات النهائية غير المكتملة ───────────────────────────────────

    /** ألغاها الطالب ضمن المهلة المسموحة (60 دقيقة). */
    case CancelledByStudent = 'cancelled_by_student';

    /** ألغاها المعلم. */
    case CancelledByTeacher = 'cancelled_by_teacher';

    /** ألغتها المؤسسة: عطلة، أو ظرف تشغيلي. */
    case CancelledBySchool = 'cancelled_by_school';

    /** لم يحضر الطالب ولم يُخطر ضمن المهلة. */
    case NoShow = 'no_show';

    /** غياب بعذر مقبول من الإدارة. */
    case Excused = 'excused';

    /** أُجّلت وأُنشئت حصة تلافي مرتبطة بها. */
    case Postponed = 'postponed';

    /** حدث مستقبلي استُبدل عند تعديل قالب الجدول، بلا أثر مالي. */
    case Superseded = 'superseded';

    /**
     * الانتقالات المسموحة. أي انتقال غير مذكور هنا مرفوض.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [
                self::Scheduled,
                self::CancelledBySchool,
            ],
            self::Scheduled => [
                self::Confirmed,
                self::InProgress,
                self::Postponed,
                self::CancelledByStudent,
                self::CancelledByTeacher,
                self::CancelledBySchool,
                self::NoShow,
                self::Excused,
                self::Superseded,
            ],
            self::Confirmed => [
                self::InProgress,
                self::Postponed,
                self::CancelledByStudent,
                self::CancelledByTeacher,
                self::CancelledBySchool,
                self::NoShow,
                self::Excused,
                self::Superseded,
            ],
            self::InProgress => [
                self::AwaitingReview,
                self::CancelledBySchool,
            ],
            self::AwaitingReview => [
                self::Completed,
                self::NoShow,
                self::Excused,
            ],

            // حالات نهائية — لا خروج منها إلا بقيدة تسوية موثّقة.
            self::Completed,
            self::CancelledByStudent,
            self::CancelledByTeacher,
            self::CancelledBySchool,
            self::NoShow,
            self::Excused,
            self::Postponed => [],
            self::Superseded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** هل هذه الحالة نهائية؟ */
    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /** هل تولّد هذه الحالة قيدة مستحقات؟ */
    public function triggersPayroll(): bool
    {
        return match ($this) {
            self::Completed,
            self::NoShow,
            self::Excused,
            self::CancelledByStudent,
            self::CancelledByTeacher,
            self::CancelledBySchool,
            self::Postponed => true,
            self::Superseded => false,
            default => false,
        };
    }

    /**
     * مفتاح النتيجة في مصفوفة config/payroll.php outcomes.
     */
    public function payrollOutcome(): ?string
    {
        return match ($this) {
            self::Completed => 'completed',
            self::NoShow => 'student_no_show',
            self::Excused => 'student_excused',
            self::CancelledByStudent => 'cancelled_accepted',
            self::CancelledByTeacher => 'teacher_absent',
            self::CancelledBySchool => 'cancelled_by_school',
            self::Postponed => 'postponed',
            self::Superseded => null,
            default => null,
        };
    }

    /** هل تُحتسب هذه الحالة مخالفة على الطالب؟ */
    public function isStudentViolation(): bool
    {
        return $this === self::NoShow;
    }

    /** هل يستطيع الطالب دخول الفصل في هذه الحالة؟ */
    public function allowsJoining(): bool
    {
        return in_array($this, [self::Scheduled, self::Confirmed, self::InProgress], true);
    }

    public function label(): string
    {
        return __('sessions::status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled, self::Confirmed => 'blue',
            self::InProgress => 'green',
            self::AwaitingReview => 'amber',
            self::Completed => 'emerald',
            self::Postponed => 'violet',
            self::Excused => 'sky',
            self::NoShow => 'red',
            self::Superseded => 'gray',
            self::CancelledByStudent, self::CancelledByTeacher, self::CancelledBySchool => 'rose',
        };
    }
}
