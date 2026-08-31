<?php

declare(strict_types=1);

namespace App\Application\Queries;

use Illuminate\Support\Facades\Lang;
use Modules\Attendance\Domain\Contracts\AttendanceAdministrationQueries;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Notifications\Domain\Contracts\NotificationAdministrationQueries;
use Modules\Recordings\Domain\Contracts\RecordingAdministrationQueries;
use Modules\Sessions\Application\Queries\SessionOperationsQueryService;
use Modules\Sessions\Domain\Models\Session;
use Modules\VirtualClassroom\Domain\Contracts\ClassroomAdministrationQueries;

/** يجمع مركز الحصة مع نتيجة الحضور دون قراءة جدول عابر لحدود الموديولات. */
final readonly class SessionOperationsCoordinator
{
    public function __construct(
        private SessionOperationsQueryService $sessions,
        private AttendanceAdministrationQueries $attendances,
        private ClassroomAdministrationQueries $classrooms,
        private RecordingAdministrationQueries $recordings,
        private NotificationAdministrationQueries $notifications,
        private UserAccountDirectory $accounts,
    ) {}

    /** @return array<string, list<array<string, mixed>>> */
    public function sessionHub(string $organizationId, Session $session): array
    {
        $hub = $this->sessions->sessionHub($organizationId, $session);
        $participantIds = array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['id'] ?? ''),
            $hub['participants'],
        )));
        $attendances = $this->attendances->byParticipantIds($organizationId, $participantIds);

        $hub['participants'] = array_map(function (array $row) use ($attendances): array {
            $attendance = $attendances[(string) $row['id']] ?? null;

            return [
                ...$row,
                'attendance_status' => $attendance === null
                    ? (string) __('attendance::messages.pending_confirmation')
                    : (string) __('attendance::status.'.$attendance->status),
                'derived_attendance_status' => $attendance === null
                    ? (string) __('sessions::fields.not_available')
                    : (string) __('attendance::status.'.$attendance->derivedStatus),
                'attendance_confirmed_at' => $attendance?->confirmedAt,
                'attendance_override_reason' => $attendance?->overrideReason,
            ];
        }, $hub['participants']);

        $classroom = $this->classrooms->findForSession($organizationId, (string) $session->getKey());
        if ($classroom === null) {
            $hub['classroom'] = [];
            $hub['classroom_events'] = [];
        } else {
            $userIds = array_values(array_unique(array_filter(array_map(
                static fn (array $event): ?string => is_string($event['user_id'] ?? null)
                    ? $event['user_id']
                    : null,
                $classroom->events,
            ))));
            $users = $this->accounts->findMany($organizationId, $userIds);
            $hub['classroom'] = [[
                'id' => $classroom->id,
                'provider' => $classroom->provider,
                'status_value' => $classroom->status,
                'status' => (string) __('virtualclassroom::status.'.$classroom->status),
                'health_status_value' => $classroom->healthStatus,
                'health_status' => (string) __('virtualclassroom::health.'.$classroom->healthStatus),
                'provision_attempts' => $classroom->provisionAttempts,
                'created_remote_at' => $classroom->createdRemoteAt,
                'started_at' => $classroom->startedAt,
                'ended_at' => $classroom->endedAt,
                'last_error' => $classroom->lastError,
                'last_error_at' => $classroom->lastErrorAt,
                'max_concurrent_participants' => $classroom->maxConcurrentParticipants,
            ]];
            $hub['classroom_events'] = array_map(static fn (array $event): array => [
                ...$event,
                'type' => (string) __('virtualclassroom::event_types.'.(string) $event['type']),
                'participant' => isset($event['user_id'], $users[(string) $event['user_id']])
                    ? $users[(string) $event['user_id']]->name
                    : ($event['external_user_id'] ?? (string) __('sessions::fields.not_available')),
            ], $classroom->events);
        }

        $hub['recordings'] = array_map(static fn (mixed $recording): array => [
            'id' => $recording->id,
            'provider' => $recording->provider,
            'status' => (string) __('recordings::status.'.$recording->status),
            'duration_minutes' => $recording->durationSeconds === null ? null : (int) ceil($recording->durationSeconds / 60),
            'active_grants' => $recording->activeGrantCount,
            'views' => $recording->viewCount,
            'downloads' => $recording->downloadCount,
            'available_from' => $recording->availableFrom,
            'expires_at' => $recording->expiresAt,
            'archived_at' => $recording->archivedAt,
        ], $this->recordings->forSession($organizationId, (string) $session->getKey()));

        $notifications = $this->notifications->forSession($organizationId, (string) $session->getKey());
        $recipients = $this->accounts->findMany($organizationId, array_values(array_unique(array_map(
            static fn (mixed $notification): string => $notification->userId,
            $notifications,
        ))));
        $hub['notifications'] = array_map(static fn (mixed $notification): array => [
            'id' => $notification->id,
            'recipient' => $recipients[$notification->userId]->name ?? (string) __('sessions::fields.not_available'),
            'category' => self::translatedLabel('notifications::categories.'.$notification->category, $notification->category),
            'channel' => self::translatedLabel('notifications::channels.'.$notification->channel, $notification->channel),
            'status' => self::translatedLabel('notifications::status.'.$notification->status, $notification->status),
            'attempts' => $notification->attempts,
            'scheduled_for' => $notification->scheduledFor,
            'sent_at' => $notification->sentAt,
            'read_at' => $notification->readAt,
            'last_error' => $notification->lastError,
        ], $notifications);

        return $hub;
    }

    private static function translatedLabel(string $key, string $fallback): string
    {
        return Lang::has($key) ? (string) __($key) : $fallback;
    }
}
