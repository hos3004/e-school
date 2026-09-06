<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Enums;

/**
 * دورة حياة اعتذار المعلم عن حصة.
 *
 * قاعدة العميل الحاكمة (docs/client-answers.md §ي):
 * **الاعتذار لا يُلغي الحصة.** الاعتماد يفتح البحث عن بديل فقط،
 * ولذلك لا توجد هنا حالة اسمها «ألغى الحصة» ولا يجوز إضافتها.
 *
 * المخطط المرجعي في docs/05-state-machines.md
 */
enum ApologyStatus: string
{
    /** حالة انتقالية قصيرة بين حفظ الطلب واعتماده التلقائي. */
    case Submitted = 'submitted';

    /** سحبه المعلم قبل صدور القرار — لا يُحتسب في سُلَّم المتابعة. */
    case Withdrawn = 'withdrawn';

    /** اعتُمد تلقائيًا: تبدأ رحلة البحث عن بديل والحصة قائمة كما هي. */
    case Approved = 'approved';

    /** رُفض تصحيحيًا بسبب مكتوب: المعلم يبقى مسندًا للحصة. */
    case Rejected = 'rejected';

    /**
     * اعتُمد الاعتذار وأُسند بديل فعلًا — نهاية المسار السعيد.
     *
     * فصلناها عن Approved لأن «معتمد بلا بديل» حالة تشغيلية خطرة
     * تحتاج تصعيدًا للإدارة قبل موعد الحصة، ولا يجوز أن تختفي داخل Approved.
     */
    case Covered = 'covered';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Submitted => [self::Approved, self::Rejected, self::Withdrawn],
            self::Approved => [self::Covered],

            self::Rejected, self::Withdrawn, self::Covered => [],
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

    /**
     * هل يُحتسب هذا الاعتذار في سُلَّم المتابعة والتصعيد؟
     *
     * المسحوب والمرفوض لا يُحتسبان: لم يقع أثر تشغيلي على الحصة.
     * القاعدة من config('discipline.teacher.apology.counts_only_when_approved').
     */
    public function countsTowardEscalation(): bool
    {
        if (config('discipline.teacher.apology.counts_only_when_approved', true) !== true) {
            return $this !== self::Withdrawn;
        }

        return in_array($this, [self::Approved, self::Covered], true);
    }

    /** هل ما زال الاعتذار ينتظر تغطية ببديل؟ */
    public function awaitsSubstitute(): bool
    {
        return $this === self::Approved;
    }

    public function label(): string
    {
        return __('sessions::apology.status.'.$this->value);
    }
}
