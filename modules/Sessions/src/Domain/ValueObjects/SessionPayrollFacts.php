<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * حقائق الحصة التي يحتاجها مستهلك خارجي لاحتساب أثر مالي أو تشغيلي.
 *
 * معرّفات وقيم أولية فقط — لا نموذج Eloquent ولا علاقة قابلة للتحميل، حتى
 * يظل جدول `sessions` مملوكًا لهذا الموديول وحده.
 */
final readonly class SessionPayrollFacts
{
    public function __construct(
        public string $sessionId,
        public string $organizationId,
        public string $courseId,
        public ?string $groupId,
        /** المعلم المنفّذ فعليًا — هو من تُحتسب له الحصة. */
        public string $staffProfileId,
        /** المعلم المسند أصلًا؛ يختلف عن المنفّذ عند وجود بديل. */
        public string $originalTeacherId,
        public string $sessionType,
        public string $status,
        public CarbonImmutable $scheduledStart,
        public CarbonImmutable $scheduledEnd,
        /** الحصة الأصلية التي جاءت هذه تلافيًا لها، إن وُجدت. */
        public ?string $makeupForSessionId,
        /** اعتذار المعلم الأصلي معتمد، فلا يستحق الحصة بنفسه. */
        public bool $hasApprovedTeacherApology,
        /** يوجد مشارك قدّم اعتذار طالب مسجّلًا على الحصة. */
        public bool $hasStudentApology,
    ) {}

    public function isMakeup(): bool
    {
        return $this->makeupForSessionId !== null;
    }

    public function hasSubstitute(): bool
    {
        return $this->staffProfileId !== $this->originalTeacherId;
    }
}
