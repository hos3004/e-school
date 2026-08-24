<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Services;

use Modules\Groups\Domain\Contracts\GroupPlacementGateway;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Groups\Domain\Models\GroupProgram;
use Modules\Groups\Domain\Models\GroupTeacher;
use Modules\Groups\Domain\ValueObjects\GroupPlacementData;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class GroupPlacementService implements GroupPlacementGateway
{
    public function __construct(
        private Transaction $transaction,
    ) {}

    public function placeStudent(
        string $organizationId,
        string $groupId,
        string $programId,
        ?string $courseId,
        string $studentProfileId,
        bool $requiresSingleMember,
    ): GroupPlacementData {
        return $this->transaction->run(function () use (
            $organizationId,
            $groupId,
            $programId,
            $courseId,
            $studentProfileId,
            $requiresSingleMember,
        ): GroupPlacementData {
            /** @var Group|null $group */
            $group = Group::query()
                ->forOrganization($organizationId)
                ->lockForUpdate()
                ->find($groupId);

            if ($group === null) {
                throw BusinessRuleViolation::make(
                    'groups.group_not_found',
                    'groups::errors.group_not_found',
                );
            }

            if (!$group->status->acceptsEnrollment()) {
                throw BusinessRuleViolation::make(
                    'groups.group_not_open',
                    'groups::errors.group_not_accepting_members',
                    ['status' => $group->status->label()],
                );
            }

            $programAttached = GroupProgram::query()
                ->forGroup($groupId)
                ->forProgram($programId)
                ->exists();

            if (!$programAttached) {
                throw BusinessRuleViolation::make(
                    'groups.program_not_attached',
                    'groups::errors.program_not_attached',
                    ['program_id' => $programId],
                );
            }

            $teachers = GroupTeacher::query()
                ->forGroup($groupId)
                ->open()
                ->when(
                    $courseId !== null,
                    static fn ($query) => $query->where('course_id', $courseId),
                )
                ->get(['staff_profile_id', 'course_id']);

            if ($courseId !== null && $teachers->isEmpty()) {
                throw BusinessRuleViolation::make(
                    'groups.course_not_assigned',
                    'groups::errors.course_not_assigned',
                    ['course_id' => $courseId],
                );
            }

            /** @var GroupMembership|null $existing */
            $existing = GroupMembership::query()
                ->forGroup($groupId)
                ->forStudent($studentProfileId)
                ->active()
                ->first();

            if ($existing !== null) {
                return $this->toData($existing, $group, $programId, $courseId, $teachers->all(), false);
            }

            $activeMembers = GroupMembership::query()
                ->forGroup($groupId)
                ->active()
                ->count();

            if ($activeMembers >= $group->capacity) {
                throw BusinessRuleViolation::make(
                    'groups.capacity_reached',
                    'groups::errors.capacity_reached',
                    ['capacity' => $group->capacity],
                );
            }

            if ($requiresSingleMember && $activeMembers > 0) {
                throw BusinessRuleViolation::make(
                    'groups.individual_course_requires_empty_group',
                    'groups::errors.individual_course_requires_empty_group',
                );
            }

            $membership = GroupMembership::query()->create([
                'group_id' => (string) $group->getKey(),
                'student_profile_id' => $studentProfileId,
                'joined_at' => now()->utc(),
                'status' => MembershipStatus::Active,
            ]);

            return $this->toData($membership, $group, $programId, $courseId, $teachers->all(), true);
        });
    }

    /**
     * @param list<GroupTeacher> $teachers
     */
    private function toData(
        GroupMembership $membership,
        Group $group,
        string $programId,
        ?string $courseId,
        array $teachers,
        bool $created,
    ): GroupPlacementData {
        return new GroupPlacementData(
            membershipId: (string) $membership->getKey(),
            groupId: (string) $group->getKey(),
            organizationId: (string) $group->organization_id,
            programId: $programId,
            courseId: $courseId,
            studentProfileId: (string) $membership->student_profile_id,
            teacherAssignments: array_values(array_map(
                static fn (GroupTeacher $teacher): array => [
                    'staff_profile_id' => (string) $teacher->staff_profile_id,
                    'course_id' => $teacher->course_id !== null ? (string) $teacher->course_id : null,
                ],
                $teachers,
            )),
            created: $created,
        );
    }
}
