<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Enums;

/**
 * حالة صحة الفصل المباشر كما يراها مزوّد الخدمة.
 *
 * تُحدَّث عبر فحص الصحة الدوري وقبل موجة الحصص، وتُستخدم لتحذير
 * الإدارة مبكرًا قبل أن يتحول عطل المزوّد إلى حصة ضائعة.
 */
enum ClassroomHealthStatus: string
{
    /** لم نفحص الفصل بعد. */
    case Unknown = 'unknown';

    /** المزوّد يعمل والفصل جاهز للانضمام. */
    case Healthy = 'healthy';

    /** المزوّد يعمل ببطء أو بمشاكل جزئية — يحتاج مراقبة. */
    case Degraded = 'degraded';

    /** المزوّد أو الفصل غير متاح — يجب التصعيد فورًا. */
    case Down = 'down';

    /**
     * الانتقالات المسموحة. أي انتقال غير مذكور هنا مرفوض.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Unknown => [
                self::Healthy,
                self::Degraded,
                self::Down,
            ],
            self::Healthy => [
                self::Degraded,
                self::Down,
                self::Unknown,
            ],
            self::Degraded => [
                self::Healthy,
                self::Down,
                self::Unknown,
            ],
            self::Down => [
                self::Healthy,
                self::Degraded,
                self::Unknown,
            ],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** هل الفصل قابل للاستخدام الآن؟ */
    public function isUsable(): bool
    {
        return in_array($this, [self::Unknown, self::Healthy], true);
    }

    /** هل هذه الحالة تستوجب تنبيهًا إداريًا؟ */
    public function requiresAttention(): bool
    {
        return in_array($this, [self::Degraded, self::Down], true);
    }

    public function label(): string
    {
        return __('virtualclassroom::health.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Unknown => 'gray',
            self::Healthy => 'green',
            self::Degraded => 'amber',
            self::Down => 'red',
        };
    }
}
