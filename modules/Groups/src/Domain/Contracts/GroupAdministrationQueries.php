<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\Contracts;

use Modules\Groups\Domain\ValueObjects\GroupMemberData;
use Modules\Groups\Domain\ValueObjects\PlacementGroupData;
use Modules\Groups\Domain\ValueObjects\SchedulingGroupData;
use Modules\Groups\Domain\ValueObjects\StudentGroupMembershipData;
use Modules\Groups\Domain\ValueObjects\TeacherGroupAssignmentData;

/** استعلامات إدارة المجموعات للواجهات المركبة، وتعيد DTOs فقط. */
interface GroupAdministrationQueries
{
    /**
     * @param list<string> $groupIds
     * @return array<string, SchedulingGroupData>
     */
    public function groupsByIds(string $organizationId, array $groupIds): array;

    /** @return list<SchedulingGroupData> */
    public function activeGroupsForScheduling(string $organizationId): array;

    /**
     * المجموعات النشطة التي تقبل تسكينًا فوريًا في هذا الكورس.
     *
     * @return list<PlacementGroupData>
     */
    public function availableForPlacement(
        string $organizationId,
        string $programId,
        string $courseId,
    ): array;

    /**
     * المجموعات المفتوحة للتسكين الجماعي — النشطة والمسودّات معًا.
     *
     * المسودّة تُدرَج بلا اشتراط معلم للكورس، لأن المعلم من البيانات المؤجَّلة
     * التي تُستوفى عند التفعيل. المجموعات الممتلئة والمُختمة والملغاة مستبعدة.
     *
     * @return list<PlacementGroupData>
     */
    public function openForPlacement(
        string $organizationId,
        string $programId,
        ?string $courseId,
    ): array;

    /**
     * مجموعة واحدة بعينها إن كانت مفتوحة للتسكين داخل هذه المؤسسة.
     *
     * تُعيد `null` للمجموعة المُختمة أو المؤرشفة أو التابعة لمؤسسة أخرى — وهو
     * ما يجعلها بوابة تحقق من المعرّف الوارد من المتصفح لا مجرد قراءة.
     */
    public function openGroupForPlacement(string $organizationId, string $groupId): ?PlacementGroupData;

    /** @return list<StudentGroupMembershipData> */
    public function membershipsForStudent(string $organizationId, string $studentProfileId): array;

    /** @return list<TeacherGroupAssignmentData> */
    public function assignmentsForTeacher(string $organizationId, string $staffProfileId): array;

    /** @return list<string> */
    public function programIdsForGroup(string $organizationId, string $groupId): array;

    /** @return list<GroupMemberData> */
    public function membershipsForGroup(string $organizationId, string $groupId): array;

    /** @return list<TeacherGroupAssignmentData> */
    public function assignmentsForGroup(string $organizationId, string $groupId): array;

    /**
     * عدد المجموعات النشطة لكل معلم — دفعة واحدة بلا N+1.
     * «نشطة» = إسناد سارٍ (assigned_to فارغ أو مؤجل) على مجموعة حالتها active.
     *
     * @param list<string> $staffProfileIds
     * @return array<string, int> مفتوحة بمعرّف ملف الموظف
     */
    public function activeAssignmentCountsForTeachers(string $organizationId, array $staffProfileIds): array;
}
