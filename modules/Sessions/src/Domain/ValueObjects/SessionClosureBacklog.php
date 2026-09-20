<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\ValueObjects;

/**
 * عدّاد طابور الإقفال: المجموع وتوزيعه ومنه أقدم تاريخ.
 *
 * `byTeacher` مفاتيحه `staff_profile_id` لا أسماء — الأسماء يملكها موديول
 * Staff، ويحلّها المستهلك عبر عقده. ومجموع قيمه يساوي `total` دائمًا، فلا
 * يعرض المستهلك توزيعًا لا يجمع إلى الرقم المعروض فوقه.
 */
final readonly class SessionClosureBacklog
{
    /**
     * @param array<string, int> $byTeacher staff_profile_id ← عدد الحصص
     */
    public function __construct(
        public int $total,
        public array $byTeacher,
        public ?string $oldestScheduledEnd,
        /** كم منها أقدم من أسبوع — التراكم القديم يستحق نبرة أشد. */
        public int $olderThanAWeek,
    ) {}
}
