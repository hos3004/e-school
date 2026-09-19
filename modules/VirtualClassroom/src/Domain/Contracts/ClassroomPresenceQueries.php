<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Contracts;

use Carbon\CarbonImmutable;

interface ClassroomPresenceQueries
{
    public function wasUserPresent(
        string $sessionId,
        string $userId,
        CarbonImmutable $scheduledStart,
        CarbonImmutable $scheduledEnd,
    ): bool;

    /**
     * من هو **داخل الغرفة الآن** في كل حصة من هذه الحصص.
     *
     * `wasUserPresent` يجيب عن الماضي — هل حضر خلال الموعد الرسمي — وهو ما
     * يحتاجه الحضور والمستحقات. شاشة المتابعة تسأل سؤالًا آخر تمامًا: الحصة
     * موعدها الآن، فهل الغرفة فيها أحد في هذه اللحظة؟ الفرق هو الفرق بين
     * «حضر» و«لم يدخل بعد».
     *
     * الاستعلام مجمَّع لأن الشاشة تعرض عشرات الحصص وتُحدَّث كل دقيقة.
     *
     * @param list<string> $sessionIds
     * @return array<string, list<string>> معرّف الحصة ← معرّفات المستخدمين الحاضرين
     */
    public function currentlyPresentUserIds(array $sessionIds): array;
}
