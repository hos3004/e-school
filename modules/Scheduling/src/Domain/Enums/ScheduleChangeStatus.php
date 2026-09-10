<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Enums;

/**
 * دورة طلب تغيير الموعد الدائم للجدول.
 *
 * المعلم يقترح موعدًا أسبوعيًا جديدًا لقالب الجدول ← يصل إشعار لكل طلاب الكورس
 * وللمشرف والإدارة ← لا يُطبَّق التغيير إلا بعد قبول كل الطلاب المعنيين ←
 * رفض طالب واحد ينهي الطلب، وانقضاء المهلة ينهيه كذلك.
 *
 * الطلب لا يمس الحصص داخل نافذة القفل (config/scheduling.recurrence.edit_lock_hours)؛
 * التغيير يسري على الحصص اللاحقة فقط عبر UpdateScheduleAction.
 */
enum ScheduleChangeStatus: string
{
    /** قُدّم الطلب وينتظر رد الطلاب. */
    case Pending = 'pending';

    /** قبله كل الطلاب وطُبّق على قالب الجدول. */
    case Applied = 'applied';

    /** رفضه أحد الطلاب. */
    case Rejected = 'rejected';

    /** سحبه المعلم أو الإدارة قبل اكتمال الردود. */
    case Withdrawn = 'withdrawn';

    /** انقضت مهلة رد الطلاب دون اكتمال القبول. */
    case Expired = 'expired';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Applied, self::Rejected, self::Withdrawn, self::Expired],
            self::Applied, self::Rejected, self::Withdrawn, self::Expired => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function label(): string
    {
        return __('scheduling::schedule_change.'.$this->value);
    }
}
