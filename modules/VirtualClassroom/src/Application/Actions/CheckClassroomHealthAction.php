<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Domain\Events\ClassroomHealthChecked;
use Modules\VirtualClassroom\Domain\Models\Classroom;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class CheckClassroomHealthAction
{
    public function __construct(
        private VirtualClassroomProvider $provider,
        private SessionAdministrationQueries $sessions,
        private Transaction $transaction,
        private AuditRecorder $audit,
        private Dispatcher $events,
    ) {}

    public function execute(
        string $organizationId,
        string $sessionId,
        string $actorId,
        string $reason,
    ): Classroom {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'virtualclassroom.reason_required',
                'virtualclassroom::errors.reason_required',
            );
        }

        if ($this->sessions->findForOrganization($organizationId, $sessionId) === null) {
            throw BusinessRuleViolation::make(
                'virtualclassroom.session_not_found',
                'virtualclassroom::errors.session_not_found',
            );
        }

        /** @var Classroom|null $classroom */
        $classroom = Classroom::query()->forSession($sessionId)->first();
        if ($classroom === null || !$classroom->isProvisioned()) {
            throw BusinessRuleViolation::make(
                'virtualclassroom.not_provisioned',
                'virtualclassroom::errors.not_provisioned',
            );
        }

        $health = $this->provider->healthCheck();
        $classroom = $this->transaction->run(function () use (
            $classroom,
            $health,
            $organizationId,
            $actorId,
            $reason,
        ): Classroom {
            /** @var Classroom $locked */
            $locked = Classroom::query()->lockForUpdate()->findOrFail((string) $classroom->getKey());
            $before = ['health_status' => $locked->health_status->value, 'last_error' => $locked->last_error];
            $locked->forceFill([
                'health_status' => $health->status,
                'last_error' => $health->status->requiresAttention() ? $health->message : null,
                'last_error_at' => $health->status->requiresAttention() ? now('UTC') : null,
            ])->save();
            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'virtualclassroom.health_checked',
                auditableType: 'classrooms',
                auditableId: (string) $locked->getKey(),
                oldValues: $before,
                newValues: [
                    'health_status' => $health->status->value,
                    'message' => $health->message,
                ],
                reason: trim($reason),
            );

            return $locked;
        });

        $this->events->dispatch(new ClassroomHealthChecked(
            classroomId: (string) $classroom->getKey(),
            sessionId: $sessionId,
            provider: (string) $classroom->provider,
            status: $health->status,
            message: $health->message,
            actorId: $actorId,
        ));

        return $classroom;
    }
}
