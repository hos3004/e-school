<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use App\Http\Requests\Learning\CreateTeachingAssignmentRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Assignments\Application\Actions\CreateAssignmentAction;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Presentation\Http\Controllers\ShowAssignmentController;
use Modules\Assignments\Presentation\Http\Resources\AssignmentResource;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

final class TeachingAssignmentsController extends Controller
{
    public function __construct(private PortalData $data, private TeachingTargets $targets, private TeachingStudents $students) {}

    /** @return array{string,string} */
    private function context(Request $request): array
    {
        $org = (string) $request->user()?->getAttribute('organization_id');
        $staff = $this->data->staffProfileId((string) $request->user()?->getAuthIdentifier(), $org);
        abort_if($staff === null, 403);

        return [$org, $staff];
    }

    public function index(Request $request): Response
    {
        [$org, $staff] = $this->context($request);
        Gate::authorize('viewAny', Assignment::class);

        return Inertia::render('Learning/TeachingAssignments', [
            'timezone' => app(ConsoleContext::class)->forRequest($request)['timezone'],
            'targets' => array_values($this->targets->forTeacher($org, $staff)),
            'students' => array_values($this->students->roster($org, $staff, 'ar')),
            'canCreate' => $request->user()?->can('assignment.manage'), 'canGrade' => $request->user()?->can('assignment.grade'),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function preview(Request $request): array
    {
        [$org, $staff] = $this->context($request);
        if (!$request->user()?->can('assignment.manage') && !$request->user()?->can('assignment.grade')) {
            return [];
        }

        return Assignment::query()->forOrganization($org)->where('staff_profile_id', $staff)->withCount('submissions')->orderByDesc('created_at')->limit(5)->get()->map(static fn (Assignment $item): array => [
            'id' => (string) $item->getKey(), 'title' => $item->title['ar'] ?? '', 'dueAt' => $item->due_at?->toIso8601String(), 'studentsCount' => $item->submissions_count,
        ])->all();
    }

    public function listing(Request $request): AnonymousResourceCollection
    {
        [$org, $staff] = $this->context($request);
        Gate::authorize('viewAny', Assignment::class);

        return AssignmentResource::collection(
            Assignment::query()->forOrganization($org)->where('staff_profile_id', $staff)->withCount('submissions')->orderByDesc('created_at')->paginate(),
        );
    }

    public function show(Request $request, Assignment $assignment): JsonResponse
    {
        [$org, $staff] = $this->context($request);
        abort_unless($org === (string) $assignment->organization_id && $staff === (string) $assignment->staff_profile_id, 404);
        $payload = app(ShowAssignmentController::class)($assignment)->response()->getData(true);
        $ids = array_column($payload['data']['submissions'] ?? [], 'student_profile_id');
        $profiles = app(StudentDirectoryQueries::class)->byIds($org, $ids);
        $accounts = app(UserAccountDirectory::class)->findMany($org, array_map(static fn ($profile): string => $profile->userId, array_values($profiles)));
        $roster = $request->user()?->can('student.view') ? $this->students->roster($org, $staff, 'ar') : [];
        foreach ($payload['data']['submissions'] as &$submission) {
            $profile = $profiles[$submission['student_profile_id']] ?? null;
            $submission['student_name'] = $profile === null ? __('learning.teaching.former_student') : (isset($accounts[$profile->userId]) ? $accounts[$profile->userId]->name : $profile->studentCode);
            $submission['profile_url'] = isset($roster[$submission['student_profile_id']]) ? route('learning.teacher.students.show', ['student' => $submission['student_profile_id']]) : null;
        }
        unset($submission);

        return response()->json($payload);
    }

    public function store(CreateTeachingAssignmentRequest $request, CreateAssignmentAction $create): RedirectResponse
    {
        [$org, $staff] = $this->context($request);
        Gate::authorize('create', Assignment::class);
        $target = $this->targets->forTeacher($org, $staff)[$request->string('target')->toString()] ?? null;
        abort_if($target === null, 403);
        $timezone = (string) app(ConsoleContext::class)->forRequest($request)['timezone'];
        $payload = [
            'course_id' => $target['courseId'], 'group_id' => $target['groupId'], 'staff_profile_id' => $staff,
            'title' => $request->validated('title'), 'instructions' => $request->validated('instructions'),
            'assigned_at' => now('UTC')->toIso8601String(),
            'due_at' => CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $request->string('due_local')->toString(), $timezone)->utc()->toIso8601String(),
            'max_score' => $request->input('max_score'), 'allows_late' => $request->boolean('allows_late'),
            'late_penalty_percent' => $request->input('late_penalty_percent', 0),
            'reason' => __('learning.teaching.create_audit'),
        ];
        $create->execute([...$payload, 'organization_id' => $org], (string) $request->user()?->getAuthIdentifier(), (string) $payload['reason']);

        return back()->with('success', __('learning.teaching.created'));
    }
}
