<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Contracts;

use Carbon\CarbonImmutable;
use Modules\Sessions\Domain\ValueObjects\SessionAdministrationData;

interface SessionAdministrationQueries
{
    public function findForOrganization(
        string $organizationId,
        string $sessionId,
    ): ?SessionAdministrationData;

    /** @return list<string> */
    public function sessionIdsForOrganization(string $organizationId): array;

    /** @return list<string> */
    public function sessionIdsForTeacher(string $organizationId, string $staffProfileId): array;

    public function organizationIdForSession(string $sessionId): ?string;

    /** @return list<SessionAdministrationData> */
    public function upcomingForClassroomProvisioning(
        CarbonImmutable $from,
        CarbonImmutable $until,
        int $limit,
    ): array;

    /** @return list<SessionAdministrationData> */
    public function forStudent(string $organizationId, string $studentProfileId, int $limit): array;

    /** @return list<SessionAdministrationData> */
    public function forTeacher(string $organizationId, string $staffProfileId, int $limit): array;

    /** @return list<SessionAdministrationData> */
    public function forGroup(string $organizationId, string $groupId, int $limit): array;

    /** @return list<SessionAdministrationData> */
    public function forSchedule(string $organizationId, string $scheduleId, int $limit): array;

    /** @return list<string> ISO-8601 UTC session start times in chronological order. */
    public function startsForSchedule(string $organizationId, string $scheduleId, int $limit): array;

    /**
     * @param list<string> $statuses
     * @param list<string> $sessionTypes
     * @return list<SessionAdministrationData>
     */
    public function forReport(
        string $organizationId,
        CarbonImmutable $fromUtc,
        CarbonImmutable $untilUtcExclusive,
        array $statuses = [],
        ?string $studentProfileId = null,
        ?string $staffProfileId = null,
        ?string $groupId = null,
        ?string $courseId = null,
        array $sessionTypes = [],
        ?string $originalStaffProfileId = null,
        ?int $limit = null,
        ?CarbonImmutable $afterScheduledStart = null,
        ?string $afterId = null,
    ): array;

    /**
     * خريطة معرّف الحصة ← عنوان قابل للعرض (بديل عن ULID خام).
     *
     * @param list<string> $sessionIds
     * @return array<string, string>
     */
    public function labelsForSessions(string $organizationId, array $sessionIds): array;

    /**
     * مؤشرات حصص المعلمين دفعة واحدة بلا N+1:
     * - upcoming: حصص مجدولة/مؤكدة لم تبدأ بعد.
     * - completed: مكتملة خلال الشهر الذي يبدأ من monthStart.
     * - cancelled: ملغاة (بأي سبب) خلال الشهر نفسه.
     *
     * @param list<string> $staffProfileIds
     * @return array<string, array{upcoming: int, completed: int, cancelled: int}>
     */
    public function countsForTeachers(string $organizationId, array $staffProfileIds, CarbonImmutable $monthStart, ?CarbonImmutable $monthEnd = null): array;

    /**
     * هوية الغرفة الدائمة التي تخص هذه الحصة: معرّف الجدول المتكرر الذي
     * وُلِّدت منه، أو — لحصة تلافٍ لا تحمل جدولًا مباشرة — معرّف جدول الحصة
     * الأصلية التي تعوّضها (بتتبع سلسلة makeup_for_session_id).
     *
     * ترجع null لحصة مفردة بلا جدول ولا تعويض (تجريبية أو ويبينار)، وعندها
     * تُمنح غرفة خاصة بها فقط كما كان الحال قبل هذه الميزة.
     */
    public function roomIdentityForSession(string $organizationId, string $sessionId): ?string;

    /**
     * الحصة القابلة للدخول الآن على هذا الجدول: إما حصته العادية أو حصة
     * التلافي القائمة مقام حصة ملغاة منه، أيهما تقع الآن ضمن نافذة الدخول.
     *
     * تُستخدم لحلّ الرابط الدائم الذي يرسله المعلم للطالب مرة واحدة.
     */
    public function currentJoinableForSchedule(
        string $organizationId,
        string $scheduleId,
        CarbonImmutable $asOf,
        int $beforeMinutes,
        int $afterMinutes,
    ): ?SessionAdministrationData;
}
