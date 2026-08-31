<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Sessions\Application\Concerns\TransitionsSessionStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\SessionScheduled;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionStatusHistory;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Shared\Support\BusinessRuleViolation;

/**
 * إنشاء حصة جديدة وجدولتها وإعلانها للطرفين.
 */
final readonly class ScheduleSessionAction
{
    use TransitionsSessionStatus;

    public function __construct(
        private Dispatcher $events,
        private AuditRecorder $audit,
        private AcademicCatalogQueries $academics,
        private GroupAdministrationQueries $groups,
        private StaffQueries $staff,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data, ?string $actorId = null, ?string $reason = null): Session
    {
        $organizationId = (string) ($data['organization_id'] ?? '');
        $courseId = (string) ($data['course_id'] ?? '');
        $teacherId = (string) ($data['staff_profile_id'] ?? '');
        $groupId = isset($data['group_id']) ? (string) $data['group_id'] : null;
        if ($organizationId === ''
            || !isset($this->academics->coursesByIds($organizationId, [$courseId])[$courseId])
            || !$this->staff->isActiveTeacherForOrganization($organizationId, $teacherId)
            || ($groupId !== null && !isset($this->groups->groupsByIds($organizationId, [$groupId])[$groupId]))) {
            throw BusinessRuleViolation::make(
                'sessions.invalid_scheduling_context',
                'sessions::errors.invalid_scheduling_context',
            );
        }

        $sessionTypes = config('academic.session_types');
        if (!is_array($sessionTypes) || !array_key_exists((string) ($data['session_type'] ?? ''), $sessionTypes)) {
            throw BusinessRuleViolation::make(
                'sessions.invalid_session_type',
                'sessions::errors.invalid_session_type',
            );
        }

        $start = CarbonImmutable::parse($data['scheduled_start'], 'UTC');
        $end = CarbonImmutable::parse($data['scheduled_end'], 'UTC');

        if ($start->lessThan(CarbonImmutable::now('UTC'))) {
            throw BusinessRuleViolation::make(
                'sessions.start_in_past',
                'sessions::errors.start_in_past',
            );
        }

        if ($end->lessThanOrEqualTo($start)) {
            throw BusinessRuleViolation::make(
                'sessions.end_before_start',
                'sessions::errors.end_before_start',
            );
        }

        /** @var list<array{id: string, scheduled_start: string, scheduled_end: string}> $overlaps */
        $overlaps = Session::query()
            ->select(['id', 'scheduled_start', 'scheduled_end'])
            ->where('organization_id', $data['organization_id'])
            ->where(static function ($query) use ($data): void {
                $query->where('staff_profile_id', $data['staff_profile_id']);
                if (($data['group_id'] ?? null) !== null) {
                    $query->orWhere('group_id', $data['group_id']);
                }
            })
            ->whereIn('status', [
                SessionStatus::Draft,
                SessionStatus::Scheduled,
                SessionStatus::Confirmed,
                SessionStatus::InProgress,
                SessionStatus::AwaitingReview,
            ])
            ->whereNull('deleted_at')
            ->where('scheduled_start', '<', $end)
            ->where('scheduled_end', '>', $start)
            ->get()
            ->all();

        if ($overlaps !== []) {
            throw BusinessRuleViolation::make(
                'sessions.teacher_double_booking',
                'sessions::errors.teacher_double_booking',
                ['conflicting_session_id' => $overlaps[0]['id']],
            );
        }

        $reason = trim((string) $reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make('sessions.reason_required', 'sessions::errors.reason_required');
        }

        [$session, $event] = DB::transaction(function () use ($data, $start, $end, $actorId, $reason): array {
            $session = new Session;
            $session->fill([
                ...$data,
                'scheduled_start' => $start,
                'scheduled_end' => $end,
                'status' => SessionStatus::Scheduled,
            ]);
            $session->save();

            SessionStatusHistory::query()->create([
                'session_id' => $session->id,
                'from_status' => null,
                'to_status' => SessionStatus::Scheduled->value,
                'reason' => $reason,
                'changed_by' => $actorId,
                'changed_at' => CarbonImmutable::now('UTC'),
            ]);

            $this->audit->record(
                organizationId: (string) $session->organization_id,
                actorId: $actorId,
                actorType: $actorId === null ? 'system' : 'user',
                action: 'sessions.session_scheduled',
                auditableType: 'sessions',
                auditableId: (string) $session->getKey(),
                oldValues: null,
                newValues: [
                    'status' => SessionStatus::Scheduled->value,
                    'group_id' => $session->group_id,
                    'course_id' => $session->course_id,
                    'staff_profile_id' => $session->staff_profile_id,
                    'scheduled_start' => $start->toIso8601String(),
                    'scheduled_end' => $end->toIso8601String(),
                ],
                reason: $reason,
            );

            return [$session, new SessionScheduled(
                sessionId: $session->id,
                organizationId: $session->organization_id,
                courseId: $session->course_id,
                staffProfileId: $session->staff_profile_id,
                scheduledStart: $start->toIso8601String(),
                scheduledEnd: $end->toIso8601String(),
                groupId: $session->group_id,
            )];
        });

        $this->events->dispatch($event);

        return $session;
    }
}
