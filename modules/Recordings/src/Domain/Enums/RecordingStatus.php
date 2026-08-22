<?php

declare(strict_types=1);

namespace Modules\Recordings\Domain\Enums;

/**
 * دورة حياة ملف التسجيل بعد انتهاء الحصة.
 *
 * الاحتفاظ الافتراضي 30 يومًا (config('recordings.retention_days'))،
 * وبعده يُنفَّذ ما في config('recordings.on_expiry'):
 * أرشفة باردة ثم حذف من التخزين الساخن.
 *
 * أي انتقال يمر إجباريًا عبر canTransitionTo.
 */
enum RecordingStatus: string
{
    /** المزوّد ما زال يعالج الملف بعد انتهاء الحصة. */
    case Processing = 'processing';

    /** الملف جاهز ومتاح للمشاهدة عبر رابط موقّع مؤقت. */
    case Ready = 'ready';

    /** نُقل إلى الأرشيف البارد بعد انتهاء مدة الاحتفاظ. */
    case Archived = 'archived';

    /** فشل المزوّد في معالجة الملف — لا يتوفر للطلاب. */
    case Failed = 'failed';

    /** انتهت مدته نهائيًا وحُذف من التخزين. */
    case Expired = 'expired';

    /**
     * الانتقالات المسموحة. أي انتقال غير مذكور هنا مرفوض.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Processing => [
                self::Ready,
                self::Failed,
                self::Expired,
            ],
            self::Ready => [
                self::Archived,
                self::Expired,
            ],
            self::Archived => [
                self::Expired,
            ],

            // حالات نهائية — لا خروج منها.
            self::Failed,
            self::Expired => [],
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

    /** هل يمكن مشاهدة الملف في هذه الحالة؟ */
    public function isWatchable(): bool
    {
        return $this === self::Ready;
    }

    public function label(): string
    {
        return __('recordings::status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Processing => 'amber',
            self::Ready => 'green',
            self::Archived => 'violet',
            self::Failed => 'red',
            self::Expired => 'gray',
        };
    }
}
