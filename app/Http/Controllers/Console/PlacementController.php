<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Application\Actions\BulkAssignStudentsToGroupAction;
use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\PlacementIndexRequest;
use App\Http\Requests\Console\PlacementRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Scheduling\Application\Queries\GroupPlacementScheduleQueries;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Application\Services\ConsoleRegistrationService;
use Shared\Support\BusinessRuleViolation;

final class PlacementController extends Controller
{
    public function __construct(
        private ConsoleRegistrationService $registrations,
        private AcademicCatalogQueries $catalog,
        private GroupAdministrationQueries $groups,
        private StaffQueries $staff,
        private GroupPlacementScheduleQueries $schedules,
        private BulkAssignStudentsToGroupAction $place,
        private ConsoleContext $context,
    ) {}

    public function index(PlacementIndexRequest $request): Response
    {
        $organizationId = $this->organizationId($request);
        $filters = $request->validated();
        $filters['status'] ??= 'waiting';
        $filters['scope'] ??= 'course';
        $context = $this->context->forRequest($request);
        if (!empty($filters['application'])) {
            $focused = $this->registrations->placementRowsByIds($organizationId, [$filters['application']], $context['timezone'])[0];
            $filters['course'] ??= $focused['preferred_course_id'];
        }
        $catalog = $this->courses($organizationId);
        $course = empty($filters['course']) ? null : $this->course($organizationId, $filters['course']);
        $applications = $this->registrations->placementApplications($organizationId, $filters, $context['timezone']);
        $rows = $this->withMemberships($organizationId, $applications['data']);
        $applications['data'] = $rows;

        return Inertia::render('Console/Placement', [
            'filters' => $filters, 'catalog' => $catalog, 'applications' => $applications,
            'groups' => $course === null ? [] : $this->groupData($organizationId, $course['program_id'], $course['id'], $this->groupIds($rows)),
            'timezone' => $context['school']['timezone'],
            'canReview' => (bool) $request->user()?->can('student.create'),
        ]);
    }

    public function preflight(PlacementRequest $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);
        $data = $request->validated();
        $this->registrations->authorizePlacement($organizationId, $data['application_ids']);
        $course = $this->course($organizationId, $data['course_id']);
        $this->validateGroup($organizationId, $course['program_id'], $data['group_id'] ?? null);
        $check = $this->place->preflight($organizationId, $data['application_ids'], $data['group_id'] ?? null);

        return response()->json([
            'eligible_count' => $check->eligibleCount(), 'remaining_seats' => $check->remainingSeats,
            'capacity_warning' => $check->capacityWarning, 'group_is_draft' => $check->groupIsDraft,
            'candidates' => array_map(static fn ($candidate): array => [
                'id' => $candidate->applicationId, 'name' => $candidate->name, 'eligible' => $candidate->eligible,
                'already_member' => $candidate->alreadyMember, 'reason' => $candidate->reason,
            ], $check->candidates),
        ]);
    }

    public function store(PlacementRequest $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);
        $data = $request->validated();
        $this->registrations->authorizePlacement($organizationId, $data['application_ids']);
        $course = $this->course($organizationId, $data['course_id']);
        $this->validateGroup($organizationId, $course['program_id'], $data['group_id'] ?? null);
        $context = $this->context->forRequest($request);
        try {
            $result = $this->place->execute(
                actorOrganizationId: $organizationId, applicationIds: $data['application_ids'],
                programId: $course['program_id'], courseId: $course['id'], groupId: $data['group_id'] ?? null,
                newGroupName: empty($data['new_group_name']) ? null : ['ar' => $data['new_group_name']],
                timezone: $context['school']['timezone'], reason: __('console_registration.placement.audit'),
                actorId: (string) $request->user()?->getAuthIdentifier(),
            );
        } catch (BusinessRuleViolation $exception) {
            throw ValidationException::withMessages(['form' => $exception->getMessage()]);
        }
        $rows = $this->withMemberships($organizationId, $this->registrations->placementRowsByIds($organizationId, $data['application_ids'], $context['timezone']));

        return response()->json([
            'message' => __('console_registration.placement.saved'),
            'group_id' => $result->groupId, 'group_is_draft' => $result->groupIsDraft,
            'placed_count' => $result->placedCount(), 'skipped_existing_count' => $result->skippedExistingCount,
            'applications' => $rows,
            'groups' => $this->groupData($organizationId, $course['program_id'], $course['id'], $this->groupIds($rows)),
        ]);
    }

    /** @return list<array<string, string>> */
    private function courses(string $organizationId): array
    {
        $result = [];
        foreach ($this->catalog->programs($organizationId) as $program) {
            foreach ($this->catalog->courses($organizationId, $program->id) as $course) {
                $result[] = ['id' => $course->id, 'name' => $course->name['ar'] ?? $course->code, 'program_id' => $program->id, 'program_name' => $program->name['ar'] ?? $program->code];
            }
        }

        return $result;
    }

    /** @return array{id: string, program_id: string} */
    private function course(string $organizationId, string $courseId): array
    {
        $course = $this->catalog->coursesByIds($organizationId, [$courseId])[$courseId] ?? null;
        abort_if($course === null || $course->programId === null, 404);

        return ['id' => $course->id, 'program_id' => $course->programId];
    }

    private function validateGroup(string $organizationId, string $programId, ?string $groupId): void
    {
        if ($groupId === null) {
            return;
        }
        $group = $this->groups->groupsByIds($organizationId, [$groupId])[$groupId] ?? null;
        abort_if($group === null, 404);
        if (!in_array($programId, $group->programIds, true) || $this->groups->openGroupForPlacement($organizationId, $groupId) === null) {
            throw ValidationException::withMessages(['group_id' => __('console_registration.placement.group_unavailable')]);
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function withMemberships(string $organizationId, array $rows): array
    {
        return array_map(function (array $row) use ($organizationId): array {
            $memberships = empty($row['student_profile_id']) ? [] : $this->groups->membershipsForStudent($organizationId, $row['student_profile_id']);
            $row['memberships'] = array_values(array_map(static fn ($membership): array => [
                'group_id' => $membership->groupId, 'group_name' => $membership->groupName['ar'] ?? $membership->groupCode,
                'status' => $membership->membershipStatus,
            ], array_filter($memberships, static fn ($membership): bool => $membership->leftAt === null && (MembershipStatus::tryFrom($membership->membershipStatus)?->occupiesSeat() ?? false))));

            return $row;
        }, $rows);
    }

    /** @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private function groupIds(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            foreach ($row['memberships'] as $membership) {
                $ids[] = $membership['group_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /** @param list<string> $extraIds
     * @return list<array<string, mixed>>
     */
    private function groupData(string $organizationId, string $programId, string $courseId, array $extraIds): array
    {
        $open = $this->groups->openForPlacement($organizationId, $programId, $courseId);
        $openIds = array_map(static fn ($group): string => $group->id, $open);
        $groupIds = array_values(array_unique([...$openIds, ...$extraIds]));
        $details = $this->groups->groupsByIds($organizationId, $groupIds);
        $schedules = $this->schedules->forGroups($organizationId, $courseId, $groupIds);
        $teacherIds = [];
        foreach ($details as $group) {
            foreach ($group->teacherAssignments as $assignment) {
                $teacherIds[] = $assignment->staffProfileId;
            }
        }
        foreach ($schedules as $items) {
            foreach ($items as $schedule) {
                $teacherIds[] = $schedule['staff_profile_id'];
            }
        }
        $names = $this->staff->namesForProfiles($organizationId, array_values(array_unique($teacherIds)));
        $result = [];
        foreach ($details as $id => $group) {
            if (!in_array($programId, $group->programIds, true)) {
                continue;
            }
            $placement = collect($open)->firstWhere('id', $id) ?? $this->groups->openGroupForPlacement($organizationId, $id);
            $teachers = $placement->teacherProfileIds ?? array_map(static fn ($assignment): string => $assignment->staffProfileId, $group->teacherAssignments);
            $result[] = [
                'id' => $id, 'name' => $group->name['ar'] ?? $group->code, 'code' => $group->code,
                'status' => $group->status, 'timezone' => $group->timezone, 'starts_on' => $group->startsOn, 'ends_on' => $group->endsOn,
                'capacity' => $placement?->capacity, 'occupied_seats' => $placement?->occupiedSeats,
                'remaining_seats' => $placement?->remainingSeats, 'can_select' => in_array($id, $openIds, true),
                'teachers' => array_values(array_filter(array_map(static fn (string $id): ?string => $names[$id] ?? null, $teachers))),
                'schedules' => array_map(static fn (array $schedule): array => [...$schedule, 'teacher_name' => $names[$schedule['staff_profile_id']] ?? null], $schedules[$id] ?? []),
            ];
        }

        return $result;
    }

    private function organizationId(Request $request): string
    {
        $id = (string) data_get($request->user(), 'organization_id');
        abort_if($id === '', 403);

        return $id;
    }
}
