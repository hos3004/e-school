<?php

declare(strict_types=1);

namespace Modules\Recordings\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Events\RecordingRegistered;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\VirtualClassroom\Domain\Contracts\ClassroomAdministrationQueries;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تسجيل ملف تسجيل لحصة منتهية.
 *
 * القواعد:
 *  - لا تكرار لنفس (provider, external_recording_id) بين غير المحذوفة.
 *  - مدة الاحتفاظ من config('recordings.retention_days') — لا رقم داخل الكود.
 */
final readonly class RegisterRecordingAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private SessionAdministrationQueries $sessions,
        private ClassroomAdministrationQueries $classrooms,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        string $organizationId,
        string $sessionId,
        string $classroomId,
        string $provider,
        string $externalRecordingId,
        string $disk,
        string $path,
        ?string $thumbnailPath = null,
        ?int $durationSeconds = null,
        ?int $sizeBytes = null,
        ?string $actorId = null,
        ?string $reason = null,
    ): Recording {
        $session = $this->sessions->findForOrganization($organizationId, $sessionId);
        $classroom = $this->classrooms->findForSession($organizationId, $sessionId);
        if ($session === null || $classroom === null || $classroom->id !== $classroomId) {
            throw BusinessRuleViolation::make(
                'recordings.context_invalid',
                'recordings::errors.context_invalid',
            );
        }

        $duplicate = Recording::withTrashed()
            ->where('provider', $provider)
            ->where('external_recording_id', $externalRecordingId)
            ->exists();

        if ($duplicate) {
            throw BusinessRuleViolation::make(
                'recordings.duplicate_external_id',
                'recordings::errors.duplicate_external_id',
                ['provider' => $provider],
            );
        }

        $now = CarbonImmutable::now('UTC');
        $retentionDays = (int) config('recordings.retention_days');
        $expiresAt = $now->addDays($retentionDays);

        /** @var Recording $recording */
        $recording = $this->transaction->run(function () use (
            $organizationId,
            $sessionId,
            $classroomId,
            $provider,
            $externalRecordingId,
            $disk,
            $path,
            $thumbnailPath,
            $durationSeconds,
            $sizeBytes,
            $now,
            $expiresAt,
            $actorId,
            $reason,
        ): Recording {
            /** @var Recording $recording */
            $recording = Recording::query()->create([
                'organization_id' => $organizationId,
                'session_id' => $sessionId,
                'classroom_id' => $classroomId,
                'provider' => $provider,
                'external_recording_id' => $externalRecordingId,
                'status' => RecordingStatus::Processing,
                'duration_seconds' => $durationSeconds,
                'size_bytes' => $sizeBytes,
                'disk' => $disk,
                'path' => $path,
                'thumbnail_path' => $thumbnailPath,
                'available_from' => $now,
                'expires_at' => $expiresAt,
            ]);

            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: $actorId === null ? 'integration' : 'user',
                action: 'recordings.registered',
                auditableType: 'recordings',
                auditableId: (string) $recording->getKey(),
                oldValues: null,
                newValues: [
                    'session_id' => $sessionId,
                    'classroom_id' => $classroomId,
                    'provider' => $provider,
                    'status' => RecordingStatus::Processing->value,
                    'expires_at' => $expiresAt->toIso8601String(),
                ],
                reason: trim($reason ?? '') ?: (string) __('recordings::messages.provider_ingestion_reason'),
            );

            return $recording;
        });

        $this->events->dispatch(new RecordingRegistered(
            recordingId: $recording->id,
            organizationId: $recording->organization_id,
            sessionId: $recording->session_id,
            classroomId: $recording->classroom_id,
            provider: $recording->provider,
            externalRecordingId: $recording->external_recording_id,
            expiresAt: $expiresAt->toIso8601String(),
            actorId: $actorId,
        ));

        return $recording;
    }
}
