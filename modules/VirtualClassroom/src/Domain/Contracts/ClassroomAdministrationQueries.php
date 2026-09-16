<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Contracts;

use Modules\VirtualClassroom\Domain\ValueObjects\ClassroomAdministrationData;
use Modules\VirtualClassroom\Domain\ValueObjects\PersistentRoomData;

interface ClassroomAdministrationQueries
{
    public function findForSession(
        string $organizationId,
        string $sessionId,
    ): ?ClassroomAdministrationData;

    /** @return array<string, int> */
    public function summaryForOrganization(string $organizationId): array;

    /**
     * جيل الرابط الحالي لغرفة دائمة، أو 1 إن لم تُنشأ الغرفة بعد — القيمة
     * الافتراضية نفسها التي يبدأ منها classrooms.link_generation.
     */
    public function generationForRoomIdentity(string $roomIdentity): int;

    /**
     * الغرف الدائمة (المرتبطة بجدول متكرر لا بحصة واحدة) في هذه المؤسسة —
     * لعرضها في شاشة الإدارة مع خيار تدوير كل رابط على حدة.
     *
     * @return list<PersistentRoomData>
     */
    public function persistentRoomsForOrganization(string $organizationId): array;
}
