<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Application\Actions\AssignStudentToGroupAction;
use App\Application\Actions\TransferStudentAction;
use App\Http\Controllers\Console\Support\GroupPlacementOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\StudentPlacementRequest;
use App\Http\Requests\Console\StudentTransferRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\ValueObjects\StudentGroupMembershipData;
use Modules\Students\Domain\Models\StudentProfile;

/**
 * إضافة طالب قائم إلى دورة أخرى، ونقله بين المجموعات والمعلمين، من صفحة ملفه.
 *
 * الكورس هو نقطة الاختيار ومنه يُستنبط البرنامج، فلا يرسل المتصفح البرنامج.
 * كل مجموعة واردة تُتحقق عبر بوابة القراءة العامة: تابعة للمؤسسة، ومفتوحة
 * للتسكين، والبرنامج مربوط بها — فلا يكفي أن يكون المعرّف صحيح الشكل.
 */
final class StudentPlacementController extends Controller
{
    public function __construct(
        private readonly AcademicCatalogQueries $catalog,
        private readonly GroupAdministrationQueries $groups,
        private readonly GroupPlacementOptions $groupOptions,
        private readonly AssignStudentToGroupAction $assign,
        private readonly TransferStudentAction $transfer,
    ) {}

    public function options(Request $request, string $profile): JsonResponse
    {
        $student = $this->student($request, $profile);
        $input = $request->validate(['course_id' => ['nullable', 'ulid']]);
        $organizationId = (string) $student->organization_id;
        $courseId = (string) ($input['course_id'] ?? '');

        return response()->json([
            'courses' => $this->courseOptions($organizationId),
            'groups' => $courseId === '' ? [] : $this->groupOptions->forCourse($organizationId, $courseId),
        ]);
    }

    public function store(StudentPlacementRequest $request, string $profile): RedirectResponse
    {
        $student = $this->student($request, $profile);
        $organizationId = (string) $student->organization_id;
        $course = $this->course($organizationId, (string) $request->validated('course_id'));
        $groupId = $this->validatedGroupId($organizationId, $course['program_id'], (string) $request->validated('group_id'));

        $this->assign->execute(
            actorOrganizationId: $organizationId,
            studentProfileId: (string) $student->getKey(),
            programId: $course['program_id'],
            groupId: $groupId,
            courseId: $course['id'],
            actorId: $this->actorId($request),
            reason: $request->reason(),
        );

        return back()->with('success', __('console_people.placement.added'));
    }

    public function transferStudent(StudentTransferRequest $request, string $profile): RedirectResponse
    {
        $student = $this->student($request, $profile);
        $organizationId = (string) $student->organization_id;
        $course = $this->course($organizationId, (string) $request->validated('course_id'));
        $groupId = $this->validatedGroupId($organizationId, $course['program_id'], (string) $request->validated('group_id'));
        $membership = $this->currentMembership($organizationId, (string) $student->getKey(), (string) $request->validated('membership_id'));

        if ($membership->groupId === $groupId) {
            throw ValidationException::withMessages(['group_id' => __('console_people.placement.same_group')]);
        }

        $this->transfer->execute(
            actorOrganizationId: $organizationId,
            studentProfileId: (string) $student->getKey(),
            fromMembershipId: $membership->membershipId,
            fromGroupId: $membership->groupId,
            programId: $course['program_id'],
            groupId: $groupId,
            courseId: $course['id'],
            reason: $request->reason(),
            actorId: $this->actorId($request),
        );

        return back()->with('success', __('console_people.placement.transferred'));
    }

    /** @return list<array{value: string, label: string, program_id: string}> */
    private function courseOptions(string $organizationId): array
    {
        $options = [];
        foreach ($this->catalog->programs($organizationId) as $program) {
            foreach ($this->catalog->courses($organizationId, $program->id) as $course) {
                $options[] = [
                    'value' => $course->id,
                    'label' => GroupPlacementOptions::label($course->name, $course->code)
                        .' — '.GroupPlacementOptions::label($program->name, $program->code),
                    'program_id' => $program->id,
                ];
            }
        }

        return $options;
    }

    /** @return array{id: string, program_id: string} */
    private function course(string $organizationId, string $courseId): array
    {
        $course = $this->catalog->coursesByIds($organizationId, [$courseId])[$courseId] ?? null;

        if ($course === null || $course->programId === null) {
            throw ValidationException::withMessages(['course_id' => __('console_people.placement.course_unavailable')]);
        }

        return ['id' => $course->id, 'program_id' => $course->programId];
    }

    private function validatedGroupId(string $organizationId, string $programId, string $groupId): string
    {
        $group = $this->groups->openGroupForPlacement($organizationId, $groupId);

        if ($group === null || !in_array($programId, $this->groups->programIdsForGroup($organizationId, $groupId), true)) {
            throw ValidationException::withMessages(['group_id' => __('console_people.placement.group_unavailable')]);
        }

        return $group->id;
    }

    private function currentMembership(
        string $organizationId,
        string $studentProfileId,
        string $membershipId,
    ): StudentGroupMembershipData {
        foreach ($this->groups->membershipsForStudent($organizationId, $studentProfileId) as $membership) {
            if ($membership->membershipId !== $membershipId) {
                continue;
            }

            if ($membership->leftAt !== null
                || !(MembershipStatus::tryFrom($membership->membershipStatus)?->occupiesSeat() ?? false)) {
                break;
            }

            return $membership;
        }

        throw ValidationException::withMessages(['membership_id' => __('console_people.placement.membership_unavailable')]);
    }

    private function student(Request $request, string $profile): StudentProfile
    {
        $organizationId = (string) data_get($request->user(), 'organization_id');
        abort_if($organizationId === '', 403);

        /** @var StudentProfile $student */
        $student = StudentProfile::query()
            ->forOrganization($organizationId)
            ->whereKey($profile)
            ->firstOrFail();
        Gate::authorize('view', $student);

        return $student;
    }

    private function actorId(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }
}
