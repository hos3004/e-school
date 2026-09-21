<?php

declare(strict_types=1);

namespace Shared\Archive;

/**
 * حصيلة الإقفال — لقطة مجمَّدة لما جرى في الكيان قبل إخراجه من الواجهة.
 *
 * تُبنى في طبقة القراءة (Reporting، الطبقة 7) وتُمرَّر إلى أكشن الإقفال كبيانات
 * خام. بهذا لا يعتمد موديول أكاديمي في الطبقة 2 على موديولات التشغيل فوقه،
 * ويبقى الأكشن قابلًا للاختبار بلا مكدس تشغيل كامل.
 *
 * `summary` يختلف شكله باختلاف النوع عمدًا: حصيلة البرنامج ليست حصيلة المجموعة.
 * `blockers` خريطة «مفتاح ما يمنع الإقفال ← عدده»؛ فراغها شرطُ تنفيذ الإقفال،
 * ووجود عدد فيها هو ما يسمح للرسالة أن تقول للمستخدم لماذا ومقدار ما يمنعه.
 */
final readonly class ClosureSnapshot
{
    /**
     * @param array<string, mixed> $summary
     * @param array<string, int> $blockers
     */
    public function __construct(
        public array $summary,
        public array $blockers = [],
    ) {}

    public function isBlocked(): bool
    {
        return $this->blockers !== [];
    }
}
