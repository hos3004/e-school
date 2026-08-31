<?php

declare(strict_types=1);

namespace Modules\Recordings\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Events\RecordingDeleted;
use Modules\Recordings\Domain\Models\Recording;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * حذف تسجيل قبل انتهاء مدته — طلب اعتراض أو قرار إداري.
 *
 * لا حذف فعلي: تعليق SoftDeletes مع سبب موثّق إجباريًا ومعرّف من نفّذه.
 */
final readonly class DeleteRecordingAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(Recording $recording, string $reason, ?string $actorId = null): Recording
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make(
                'recordings.deletion_reason_required',
                'recordings::errors.deletion_reason_required',
            );
        }

        if ($recording->trashed()) {
            throw BusinessRuleViolation::make(
                'recordings.already_deleted',
                'recordings::errors.already_deleted',
            );
        }

        if ($recording->status === RecordingStatus::Expired) {
            throw BusinessRuleViolation::make(
                'recordings.delete_expired',
                'recordings::errors.delete_expired',
                ['status' => $recording->status->label()],
            );
        }

        $deletedById = $actorId;

        if ($deletedById === null || $deletedById === '') {
            throw BusinessRuleViolation::make(
                'recordings.deleter_required',
                'recordings::errors.deleter_required',
            );
        }

        $this->transaction->run(function () use ($recording, $reason, $deletedById): void {
            $recording->forceFill([
                'deleted_by' => $deletedById,
                'deletion_reason' => $reason,
            ])->save();

            $this->audit->record(
                organizationId: (string) $recording->organization_id,
                actorId: $deletedById,
                actorType: 'user',
                action: 'recordings.deleted',
                auditableType: 'recordings',
                auditableId: (string) $recording->getKey(),
                oldValues: ['deleted_at' => null],
                newValues: ['deleted_by' => $deletedById],
                reason: $reason,
            );

            $recording->delete();
        });

        $this->events->dispatch(new RecordingDeleted(
            recordingId: $recording->id,
            organizationId: $recording->organization_id,
            sessionId: $recording->session_id,
            deletedById: $deletedById,
            reason: $reason,
            actorId: $deletedById,
        ));

        return $recording;
    }
}
