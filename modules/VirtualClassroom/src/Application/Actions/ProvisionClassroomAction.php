<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Domain\Enums\ClassroomHealthStatus;
use Modules\VirtualClassroom\Domain\Enums\ClassroomStatus;
use Modules\VirtualClassroom\Domain\Events\ClassroomProvisioned;
use Modules\VirtualClassroom\Domain\Exceptions\ClassroomProviderException;
use Modules\VirtualClassroom\Domain\Models\Classroom;
use Modules\VirtualClassroom\Domain\ValueObjects\ClassroomSpec;
use Shared\Support\BusinessRuleViolation;

/** إنشاء قابل لإعادة المحاولة يحتفظ بالفشل المحلي بدل فقد أثره. */
final readonly class ProvisionClassroomAction
{
    public function __construct(
        private VirtualClassroomProvider $provider,
        private Dispatcher $events,
        private SessionAdministrationQueries $sessions,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        string $sessionId,
        string $title,
        ?int $maxParticipants = null,
        ?bool $recordable = null,
        ?CarbonImmutable $startsAt = null,
        ?string $organizationId = null,
        ?string $actorId = null,
        ?string $reason = null,
        bool $ensureRemoteIsRunning = false,
    ): Classroom {
        if ($organizationId !== null
            && $this->sessions->findForOrganization($organizationId, $sessionId) === null) {
            throw BusinessRuleViolation::make(
                'virtualclassroom.session_not_found',
                'virtualclassroom::errors.session_not_found',
            );
        }

        $maxParticipants ??= (int) config('virtual-classroom.capacity.max_participants_group');
        $recordable ??= (bool) config('virtual-classroom.recording.auto_record', true);
        $reason = trim($reason ?? (string) __('virtualclassroom::messages.provision_reason'));
        if ($reason === '') {
            throw BusinessRuleViolation::make(
                'virtualclassroom.reason_required',
                'virtualclassroom::errors.reason_required',
            );
        }

        $classroom = $this->prepareForProvisioning($sessionId);

        if ($classroom->isProvisioned()) {
            if (!$ensureRemoteIsRunning
                || $classroom->external_id === null
                || $this->provider->isRunning($classroom->external_id)) {
                return $classroom;
            }

            $classroom = $this->markRemoteClassroomUnavailable(
                classroom: $classroom,
                organizationId: $organizationId,
                actorId: $actorId,
                reason: $reason,
            );

            if ($classroom->isProvisioned()) {
                return $classroom;
            }

            $classroom = $this->prepareForProvisioning($sessionId);

            if ($classroom->isProvisioned()) {
                return $classroom;
            }
        }

        $spec = new ClassroomSpec(
            sessionId: $sessionId,
            title: $title,
            externalMeetingId: 'SES-'.$sessionId.($classroom->provision_attempts > 1
                ? '-R'.$classroom->provision_attempts
                : ''),
            startsAt: $startsAt,
            maxParticipants: $maxParticipants,
            recordable: $recordable,
        );

        try {
            $remote = $this->provider->createClassroom($spec);
        } catch (ClassroomProviderException $exception) {
            DB::transaction(function () use (
                $classroom,
                $exception,
                $organizationId,
                $actorId,
                $reason,
            ): void {
                /** @var Classroom $locked */
                $locked = Classroom::query()->lockForUpdate()->findOrFail((string) $classroom->getKey());
                $locked->forceFill([
                    'status' => ClassroomStatus::Failed,
                    'health_status' => ClassroomHealthStatus::Down,
                    'last_error' => $exception->getMessage(),
                    'last_error_at' => now('UTC'),
                ])->save();
                $this->audit->record(
                    organizationId: $organizationId,
                    actorId: $actorId,
                    actorType: $actorId === null ? 'system' : 'user',
                    action: 'virtualclassroom.provision_failed',
                    auditableType: 'classrooms',
                    auditableId: (string) $locked->getKey(),
                    oldValues: ['status' => ClassroomStatus::Pending->value],
                    newValues: [
                        'status' => ClassroomStatus::Failed->value,
                        'attempts' => $locked->provision_attempts,
                        'error' => $exception->getMessage(),
                    ],
                    reason: $reason,
                );
            });

            throw $exception;
        }

        $classroom = DB::transaction(function () use (
            $classroom,
            $remote,
            $organizationId,
            $actorId,
            $reason,
        ): Classroom {
            /** @var Classroom $locked */
            $locked = Classroom::query()->lockForUpdate()->findOrFail((string) $classroom->getKey());
            $before = [
                'status' => $locked->status->value,
                'attempts' => $locked->provision_attempts,
            ];
            $locked->forceFill([
                'provider' => $this->provider->name(),
                'external_id' => $remote->externalId,
                'moderator_secret' => $remote->moderatorSecret,
                'attendee_secret' => $remote->attendeeSecret,
                'external_meta' => $remote->meta,
                'created_remote_at' => $remote->createdAt,
                'status' => ClassroomStatus::Provisioned,
                'health_status' => ClassroomHealthStatus::Unknown,
                'last_error' => null,
                'last_error_at' => null,
            ])->save();
            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: $actorId === null ? 'system' : 'user',
                action: 'virtualclassroom.provisioned',
                auditableType: 'classrooms',
                auditableId: (string) $locked->getKey(),
                oldValues: $before,
                newValues: [
                    'status' => ClassroomStatus::Provisioned->value,
                    'provider' => $locked->provider,
                    'attempts' => $locked->provision_attempts,
                ],
                reason: $reason,
            );

            return $locked;
        });

        $this->events->dispatch(new ClassroomProvisioned(
            classroomId: (string) $classroom->id,
            sessionId: $sessionId,
            provider: $this->provider->name(),
            externalId: $remote->externalId,
            createdRemoteAt: $remote->createdAt->toIso8601String(),
        ));

        return $classroom;
    }

    private function prepareForProvisioning(string $sessionId): Classroom
    {
        return DB::transaction(function () use ($sessionId): Classroom {
            /** @var Classroom $record */
            $record = Classroom::query()->firstOrCreate(
                ['session_id' => $sessionId],
                [
                    'provider' => $this->provider->name(),
                    'status' => ClassroomStatus::Pending,
                    'health_status' => ClassroomHealthStatus::Unknown,
                ],
            );

            if ($record->isProvisioned()) {
                return $record;
            }

            if (!$record->status->canProvision()) {
                throw BusinessRuleViolation::make(
                    'virtualclassroom.invalid_status',
                    'virtualclassroom::errors.invalid_status',
                    ['status' => $record->status->label()],
                );
            }

            /** @var Classroom $locked */
            $locked = Classroom::query()->lockForUpdate()->findOrFail((string) $record->getKey());
            $locked->forceFill([
                'provider' => $this->provider->name(),
                'status' => ClassroomStatus::Pending,
                'provision_attempts' => (int) $locked->provision_attempts + 1,
                'last_error' => null,
                'last_error_at' => null,
            ])->save();

            return $locked;
        });
    }

    private function markRemoteClassroomUnavailable(
        Classroom $classroom,
        ?string $organizationId,
        ?string $actorId,
        string $reason,
    ): Classroom {
        return DB::transaction(function () use (
            $classroom,
            $organizationId,
            $actorId,
            $reason,
        ): Classroom {
            /** @var Classroom $locked */
            $locked = Classroom::query()->lockForUpdate()->findOrFail((string) $classroom->getKey());

            if ($locked->external_id !== $classroom->external_id
                || !in_array($locked->status, [ClassroomStatus::Provisioned, ClassroomStatus::Running], true)) {
                return $locked;
            }

            if (!$locked->status->canTransitionTo(ClassroomStatus::Failed)) {
                throw BusinessRuleViolation::make(
                    'virtualclassroom.invalid_status',
                    'virtualclassroom::errors.invalid_status',
                    ['status' => $locked->status->label()],
                );
            }

            $before = [
                'status' => $locked->status->value,
                'external_id' => $locked->external_id,
                'attempts' => $locked->provision_attempts,
            ];
            $locked->forceFill([
                'status' => ClassroomStatus::Failed,
                'health_status' => ClassroomHealthStatus::Down,
                'last_error' => 'remote_classroom_not_running',
                'last_error_at' => now('UTC'),
            ])->save();
            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: $actorId === null ? 'system' : 'user',
                action: 'virtualclassroom.remote_classroom_unavailable',
                auditableType: 'classrooms',
                auditableId: (string) $locked->getKey(),
                oldValues: $before,
                newValues: [
                    'status' => ClassroomStatus::Failed->value,
                    'attempts' => $locked->provision_attempts,
                    'error' => $locked->last_error,
                ],
                reason: $reason,
            );

            return $locked;
        });
    }
}
