<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Portal\Support\PortalData;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Assignments\Domain\Contracts\AssignmentAudienceQueries;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;

final readonly class TeachingTargets
{
    public function __construct(private PortalData $data, private AcademicCatalogQueries $academics,
        private AssignmentAudienceQueries $audiences, private GroupAdministrationQueries $groups, private TeacherQualificationQueries $qualifications) {}

    /** @return array<string, array{key:string, courseId:string, groupId:?string, title:string}> */
    public function forTeacher(string $organizationId, string $staffId): array
    {
        $ids = array_column($this->data->teacherQualifications($staffId, 'ar'), 'id');
        $courses = $this->academics->coursesByIds($organizationId, $ids);
        $groups = $this->groups->assignmentsForTeacher($organizationId, $staffId);
        $targets = [];
        foreach ($courses as $course) {
            if (!in_array($staffId, $this->qualifications->qualifiedTeacherIdsForCourse($course->id), true) || !$this->audiences->teacherCanTeachTarget($organizationId, $staffId, $course->id, null)) {
                continue;
            }
            $name = $course->name['ar'] ?? $course->code;
            $targets[$course->id] = ['key' => $course->id, 'courseId' => $course->id, 'groupId' => null, 'title' => $name.' · '.__('learning.teaching.all_course')];
            foreach ($groups as $group) {
                if ($group->groupStatus === 'active' && $this->audiences->teacherCanTeachTarget($organizationId, $staffId, $course->id, $group->groupId)) {
                    $key = $course->id.':'.$group->groupId;
                    $targets[$key] = ['key' => $key, 'courseId' => $course->id, 'groupId' => $group->groupId,
                        'title' => $name.' · '.($group->groupName['ar'] ?? $group->groupCode)];
                }
            }
        }

        return $targets;
    }
}
