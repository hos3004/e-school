<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use Illuminate\Http\Request;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Assignments\Domain\Contracts\AssignmentAudienceQueries;
use Modules\Content\Domain\Contracts\CourseMaterialLibraryQueries;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Scheduling\Domain\Contracts\IndividualTeachingAssignments;

/** Resolves enrollment / current teaching scope before asking Content for published DTOs. */
final readonly class LearningLibraryData
{
    public function __construct(
        private AssignmentAudienceQueries $audiences,
        private CourseMaterialLibraryQueries $library,
        private AcademicCatalogQueries $academics,
        private GroupAdministrationQueries $groups,
        private IndividualTeachingAssignments $individual,
    ) {}

    /** @return array{organizationId:string, courseIds:list<string>} */
    public function scope(Request $request, string $kind): array
    {
        abort_unless(in_array($kind, ['student', 'teacher'], true), 404);
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $userId = (string) $request->user()?->getAuthIdentifier();
        abort_if($organizationId === '' || $userId === '', 403);
        $audience = $this->audiences->forUser($organizationId, $userId);
        $id = $kind === 'student' ? $audience->studentProfileId : $audience->staffProfileId;
        abort_if($id === null, 403);
        $courses = $kind === 'student' ? $audience->activeCourseIds : [];
        if ($kind === 'teacher') {
            $today = now('UTC')->toDateString();
            foreach ($this->groups->assignmentsForTeacher($organizationId, $id) as $assignment) {
                if ($assignment->groupStatus !== 'active'
                    || ($assignment->assignedFrom !== null && substr($assignment->assignedFrom, 0, 10) > $today)
                    || ($assignment->assignedTo !== null && substr($assignment->assignedTo, 0, 10) < $today)) {
                    continue;
                }
                if ($assignment->courseId !== null) {
                    $courses[] = $assignment->courseId;
                } else {
                    foreach ($this->groups->programIdsForGroup($organizationId, $assignment->groupId) as $programId) {
                        foreach ($this->academics->courses($organizationId, $programId) as $course) {
                            $courses[] = $course->id;
                        }
                    }
                }
            }
            foreach ($this->individual->activeForTeacher($organizationId, $id) as $assignment) {
                $courses[] = $assignment->courseId;
            }
        }

        $courses = array_values(array_filter(array_unique($courses),
            fn (string $courseId): bool => $this->audiences->targetBelongsToOrganization($organizationId, $courseId, null),
        ));

        return ['organizationId' => $organizationId, 'courseIds' => array_keys(
            $this->academics->coursesByIds($organizationId, $courses),
        )];
    }

    /** @return array{items:list<array<string,mixed>>,courses:list<array{id:string,name:string}>,url:string} */
    public function all(Request $request, string $kind): array
    {
        $scope = $this->scope($request, $kind);
        $courses = $this->academics->coursesByIds($scope['organizationId'], $scope['courseIds']);
        $items = [];
        foreach ($this->library->publishedForCourses($scope['organizationId'], $scope['courseIds']) as $material) {
            $items[] = [
                'id' => $material->id, 'title' => $material->title['ar'] ?? __('learning_library.untitled'),
                'description' => $material->description['ar'] ?? '', 'kind' => $material->type,
                'courseId' => $material->courseId, 'course' => $courses[$material->courseId]->name['ar'] ?? __('learning_library.course'),
                'sizeBytes' => $material->sizeBytes, 'revision' => $material->revision, 'publishedAt' => $material->publishedAt,
                'url' => route('learning.'.$kind.'.library.show', ['material' => $material->id]),
                'downloadUrl' => route('learning.'.$kind.'.library.open', ['material' => $material->id]),
            ];
        }

        return ['items' => $items, 'courses' => array_values(array_map(static fn ($course): array => [
            'id' => $course->id, 'name' => $course->name['ar'] ?? __('learning_library.course'),
        ], $courses)), 'url' => route('learning.'.$kind.'.library')];
    }

    /** @return array{items:list<array<string,mixed>>,total:int,url:?string} */
    public function preview(Request $request, string $kind): array
    {
        if (!$request->user()?->can('content.view')) {
            return ['items' => [], 'total' => 0, 'url' => null];
        }
        $data = $this->all($request, $kind);

        return ['items' => array_slice($data['items'], 0, (int) config('content.library.preview_items')), 'total' => count($data['items']), 'url' => $data['url']];
    }
}
