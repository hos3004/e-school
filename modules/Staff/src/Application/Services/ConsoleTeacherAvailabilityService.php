<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Services;

use Illuminate\Support\Facades\Gate;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Staff\Application\Actions\DecideTeacherAvailabilityAction;
use Modules\Staff\Application\Actions\RemoveTeacherAvailability;
use Modules\Staff\Application\Actions\SetTeacherAvailability;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Shared\Support\Transaction;

/** Existing availability actions and policies remain the only write authority. */
final readonly class ConsoleTeacherAvailabilityService
{
    public function __construct(
        private SetTeacherAvailability $set,
        private DecideTeacherAvailabilityAction $decide,
        private RemoveTeacherAvailability $remove,
        private UserQueryService $users,
        private Transaction $transaction,
    ) {}

    /** @return array<string, mixed> */
    public function page(string $organizationId, string $teacherId): array
    {
        $teacher = $this->ownedTeacher($organizationId, $teacherId);
        $account = $this->users->findSummary($teacher->user_id);

        return [
            'id' => $teacher->id, 'name' => $account !== null && $account->organizationId === $organizationId ? $account->name : $teacher->staff_code,
            'active' => $teacher->isActive() && ($account?->isActive() ?? false),
            'approval_required' => (bool) config('scheduling.availability.teacher_requires_approval'),
            'slots' => TeacherAvailability::query()->forProfile($teacherId)->orderBy('weekday')->orderBy('start_time')->get()->map(static fn (TeacherAvailability $slot): array => [
                'id' => $slot->id, 'weekday' => $slot->weekday, 'start_time' => substr($slot->start_time, 0, 5), 'end_time' => substr($slot->end_time, 0, 5),
                'timezone' => $slot->timezone, 'effective_from' => $slot->effective_from->toDateString(), 'effective_to' => $slot->effective_to?->toDateString(),
                'approval_status' => $slot->approval_status->value, 'decision_reason' => $slot->decision_reason,
                'can_decide' => (bool) config('scheduling.availability.teacher_requires_approval') && $slot->approval_status === TeacherAvailabilityApprovalStatus::Pending && Gate::allows('approve', $slot),
                'can_remove' => (!(bool) config('scheduling.availability.teacher_requires_approval') || $slot->approval_status !== TeacherAvailabilityApprovalStatus::Approved) && Gate::allows('delete', $slot),
            ])->all(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function create(string $organizationId, string $teacherId, array $data, string $actorId): void
    {
        $this->transaction->run(function () use ($organizationId, $teacherId, $data, $actorId): void {
            $teacher = $this->ownedTeacher($organizationId, $teacherId, true);
            Gate::authorize('create', TeacherAvailability::class);
            Gate::authorize('staff.availability.create');
            $account = $this->users->findSummary($teacher->user_id);
            abort_unless($teacher->isActive() && $account !== null && $account->organizationId === $organizationId && $account->isActive(), 422, __('console_quran.availability_inactive_teacher'));
            $this->set->execute($teacher, (int) $data['weekday'], $data['start_time'], $data['end_time'], $data['timezone'],
                $data['effective_from'], $data['effective_to'] ?? null, $actorId, __('console_quran.availability_audit_create'));
        });
    }

    public function decide(string $organizationId, string $teacherId, string $slotId, string $decision, string $actorId): void
    {
        $this->transaction->run(function () use ($organizationId, $teacherId, $slotId, $decision, $actorId): void {
            $this->ownedTeacher($organizationId, $teacherId, true);
            $slot = TeacherAvailability::query()->forProfile($teacherId)->lockForUpdate()->findOrFail($slotId);
            Gate::authorize('approve', $slot);
            $this->decide->execute($slot, TeacherAvailabilityApprovalStatus::from($decision), $actorId, __('console_quran.availability_audit_'.$decision));
        });
    }

    public function remove(string $organizationId, string $teacherId, string $slotId, string $actorId): void
    {
        $this->transaction->run(function () use ($organizationId, $teacherId, $slotId, $actorId): void {
            $this->ownedTeacher($organizationId, $teacherId, true);
            $slot = TeacherAvailability::query()->forProfile($teacherId)->lockForUpdate()->findOrFail($slotId);
            Gate::authorize('delete', $slot);
            $this->remove->execute($slot, $actorId, __('console_quran.availability_audit_remove'));
        });
    }

    private function ownedTeacher(string $organizationId, string $teacherId, bool $lock = false): StaffProfile
    {
        $teacher = StaffProfile::query()->forOrganization($organizationId)->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($teacherId);
        Gate::authorize('view', $teacher);

        return $teacher;
    }
}
