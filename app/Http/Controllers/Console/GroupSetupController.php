<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Application\Actions\EnrollExistingStudentIntoGroupAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\GroupEnrollExistingStudentRequest;
use App\Http\Requests\Console\GroupSetupRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Groups\Application\Services\ConsoleSetupService;
use Shared\Support\BusinessRuleViolation;

final class GroupSetupController extends Controller
{
    public function __construct(
        private readonly ConsoleSetupService $groups,
        private readonly AcademicCatalogQueries $catalog,
        private readonly EnrollExistingStudentIntoGroupAction $enrollExistingStudent,
    ) {}

    public function save(GroupSetupRequest $request, ?string $group = null): RedirectResponse
    {
        return $this->perform($request, function (string $organizationId, string $actorId) use ($request, $group): string {
            return $this->groups->save($organizationId, $group, $request->actionData(), $actorId,
                __('console_courses.audit.'.($group === null ? 'create_groups' : 'update_groups')));
        });
    }

    public function assign(GroupSetupRequest $request, string $group): RedirectResponse
    {
        return $this->perform($request, function (string $organizationId, string $actorId) use ($request, $group): string {
            $this->groups->assign($organizationId, $group, $request->actionData(), $actorId, __('console_courses.audit.assign_teacher'));

            return $group;
        });
    }

    public function activate(GroupSetupRequest $request, string $group): RedirectResponse
    {
        return $this->perform($request, function (string $organizationId, string $actorId) use ($group): string {
            $this->groups->activate($organizationId, $group, $actorId, __('console_courses.audit.activate_group'));

            return $group;
        });
    }

    /**
     * إضافة طالب له حساب وملف بالفعل مباشرة لهذه المجموعة — خطوة واحدة
     * تجمع التأهيل للكورس (إن لزم) والتسكين الفعلي، بلا شاشة وسيطة.
     */
    public function enrollExisting(GroupEnrollExistingStudentRequest $request, string $group): RedirectResponse
    {
        $organizationId = (string) $request->user()?->organization_id;
        abort_if($organizationId === '', 403);
        $data = $request->validated();
        $course = $this->catalog->coursesByIds($organizationId, [$data['course_id']])[$data['course_id']] ?? null;
        abort_if($course === null || $course->programId === null, 404);
        // نفس شرط إسناد المعلم لكورس داخل مجموعة (ConsoleSetupService::assign):
        // الكورسات الفردية تُدار عبر الجدولة الفردية لا المجموعات.
        if ($course->sessionMode === 'individual') {
            throw ValidationException::withMessages(['form' => __('groups::errors.course_not_found')]);
        }

        try {
            $this->enrollExistingStudent->execute(
                organizationId: $organizationId,
                studentProfileId: $data['student_profile_id'],
                programId: $course->programId,
                groupId: $group,
                courseId: $course->id,
                actorId: (string) $request->user()?->getAuthIdentifier(),
            );
        } catch (BusinessRuleViolation $exception) {
            throw ValidationException::withMessages(['form' => $exception->getMessage()]);
        }

        return redirect()->route('console.groups.show', ['group' => $group])
            ->with('success', __('console_group.add_existing_student_added'));
    }

    /** @param \Closure(string, string): string $action */
    private function perform(GroupSetupRequest $request, \Closure $action): RedirectResponse
    {
        $organizationId = (string) $request->user()?->organization_id;
        abort_if($organizationId === '', 403);
        try {
            $id = $action($organizationId, (string) $request->user()?->getAuthIdentifier());
        } catch (BusinessRuleViolation $exception) {
            throw ValidationException::withMessages(['form' => $exception->getMessage()]);
        }

        return redirect()->route('console.groups.index', ['focus' => $id])->with('success', __('console_courses.saved'));
    }
}
